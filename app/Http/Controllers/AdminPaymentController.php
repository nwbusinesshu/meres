<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment;
use App\Models\Organization;
use App\Services\BarionService;
use App\Services\BillingoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminPaymentController extends Controller
{
    /**
     * Fizetések oldal – nyitott és rendezett tételek
     */
    public function index(Request $request)
    {
        $orgId = (int) $request->session()->get('org_id');

        // First, check for abandoned payments and clean them up
        $this->handleAbandonedPayments($orgId);

        $open = \DB::table('payments as p')
            ->leftJoin('assessment as a', 'a.id', '=', 'p.assessment_id')
            ->where('p.organization_id', $orgId)
            ->whereIn('p.status', ['initial', 'pending','failed'])
            ->orderByDesc('p.created_at')
            ->select('p.*', 'a.started_at', 'a.due_at', 'a.closed_at')
            ->get();

        // ✅ SIMPLE: Block button if barion_payment_id exists
        // The start() method will check Barion API before allowing new payment
        $open = $open->map(function($payment) {
            if ($payment->status === 'pending' && !empty($payment->barion_payment_id)) {
                $payment->is_blocked = true;
            } else {
                $payment->is_blocked = false;
            }
            return $payment;
        });

        $settled = \DB::table('payments as p')
            ->leftJoin('assessment as a', 'a.id', '=', 'p.assessment_id')
            ->where('p.organization_id', $orgId)
            ->where('p.status', 'paid')
            ->orderByDesc('p.created_at')
            ->select('p.*', 'a.started_at', 'a.due_at', 'a.closed_at')
            ->get();

        return view('admin.payments', compact('open','settled'));
    }

    /**
     * ✅ FIXED: Handle abandoned payments - clear barion_payment_id after 10 minutes
     * Keep status as 'pending' to allow retry
     */
    private function handleAbandonedPayments($orgId)
    {
        $tenMinutesAgo = Carbon::now()->subMinutes(10);

        $abandonedPayments = \DB::table('payments')
            ->where('organization_id', $orgId)
            ->where('status', 'pending')
            ->whereNotNull('barion_payment_id')
            ->where('updated_at', '<', $tenMinutesAgo)
            ->get();

        foreach ($abandonedPayments as $payment) {
            // Clear barion_payment_id to allow new payment attempt
            // Keep status as 'pending' - user can retry
            \DB::table('payments')
                ->where('id', $payment->id)
                ->update([
                    'barion_payment_id' => null,
                    'status' => 'pending',  // ✅ Keep as pending
                    'updated_at' => now(),
                ]);

            \Log::info('payment.abandoned.cleared', [
                'payment_id' => $payment->id,
                'old_barion_id' => $payment->barion_payment_id,
                'minutes_since_update' => Carbon::parse($payment->updated_at)->diffInMinutes(now()),
                'new_status' => 'pending',
            ]);
        }
    }

    /**
     * ✅ ENHANCED: Start payment with Barion API check first
     */
    public function start(Request $request, \App\Services\BarionService $barion)
    {
        $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
        ]);

        try {
            \DB::beginTransaction();

            $payment = \DB::table('payments')->where('id', $request->payment_id)->lockForUpdate()->first();
            if (!$payment) {
                \DB::rollBack();
                return response()->json(['success' => false, 'message' => __('payment.payment_not_found')], 404);
            }

            if ($payment->status === 'paid') {
                \DB::rollBack();
                return response()->json(['success' => false, 'message' => __('payment.already_settled')], 422);
            }

            // ✅ NEW: Check Barion API if there's an existing barion_payment_id
            if (!empty($payment->barion_payment_id) && $payment->status === 'pending') {
                try {
                    $state = $barion->getPaymentState($payment->barion_payment_id);
                    $status = strtoupper((string) ($state['Status'] ?? ''));
                    
                    \Log::info('payment.start.barion_check', [
                        'payment_id' => $payment->id,
                        'barion_id' => $payment->barion_payment_id,
                        'barion_status' => $status,
                    ]);
                    
                    // Case 1: Payment SUCCEEDED at Barion
                    if ($status === 'SUCCEEDED') {
                        \DB::table('payments')->where('id', $payment->id)->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                            'invoice_status' => 'pending',
                            'updated_at' => now(),
                        ]);
                        
                        \DB::commit();
                        
                        \Log::info('payment.start.already_succeeded', [
                            'payment_id' => $payment->id,
                            'barion_id' => $payment->barion_payment_id,
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'status' => 'already_paid',
                            'message' => __('payment.already_paid_reload'),
                            'should_reload' => true,
                        ]);
                    }
                    
                    // If still PREPARED or STARTED, block double payment
                    if (in_array($status, ['PREPARED', 'STARTED', 'IN_PROGRESS'])) {
                        \DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => __('payment.payment_in_progress')
                        ], 409);
                    }
                    
                    // If CANCELED/EXPIRED/FAILED, clear and automatically continue with new payment
                    if (in_array($status, ['CANCELED', 'EXPIRED', 'FAILED'])) {
                        \Log::info('payment.start.previous_failed', [
                            'payment_id' => $payment->id,
                            'barion_id' => $payment->barion_payment_id,
                            'barion_status' => $status,
                            'action' => 'clearing_and_continuing',
                        ]);
                        
                        \DB::table('payments')->where('id', $payment->id)->update([
                            'barion_payment_id' => null,
                            'status' => 'pending',  // ✅ Keep as pending to allow retry
                            'updated_at' => now(),
                        ]);
                        
                        // Re-fetch payment to continue with clean state
                        $payment = \DB::table('payments')->where('id', $request->payment_id)->lockForUpdate()->first();
                        
                        // ✅ Continue to create new payment automatically (don't rollback)
                    }
                    
                } catch (\Throwable $e) {
                    \Log::warning('payment.start.barion_check_failed', [
                        'payment_id' => $payment->id,
                        'barion_id' => $payment->barion_payment_id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    // If Barion API check fails, still block to be safe
                    \DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => __('payment.status_check_failed')
                    ], 500);
                }
            }

            // Use new payment structure
            $grossAmount = (float) ($payment->gross_amount ?? 0);
            $netAmount = (float) ($payment->net_amount ?? 0);
            $currency = $payment->currency ?? 'HUF';
            
            if ($grossAmount < 1) {
                \DB::rollBack();
                return response()->json(['success' => false, 'message' => __('payment.invalid_amount')], 422);
            }

            // Calculate quantity and unit price
            $configName = ($currency === 'HUF') ? 'global_price_huf' : 'global_price_eur';
            $unitPrice = (float) (\DB::table('config')->where('name', $configName)->value('value') ?? ($currency === 'HUF' ? 950 : 2.5));
            $quantity = max(1, (int) ceil($netAmount / $unitPrice));
            
            $paymentRequestId = 'pay_' . $payment->id . '_' . time();
            
            $comment = '360° értékelés';
            if (!empty($payment->assessment_id)) {
                $comment .= ' – mérés #' . $payment->assessment_id;
            }

            $payerEmail = $request->session()->get('email', null);

            \Log::info('barion.start.calling', [
                'payment_id'         => $payment->id,
                'payment_request_id' => $paymentRequestId,
                'currency'           => $currency,
                'gross_amount'       => $grossAmount,
                'unit_price'         => $unitPrice,
                'quantity'           => $quantity,
            ]);

            // Call updated Barion service
            $started = $barion->startPayment(
                $paymentRequestId,
                $currency,
                $grossAmount,
                $unitPrice,
                $quantity,
                $comment,
                $payerEmail
            );

            \Log::info('barion.start.response', [
                'payment_id' => $payment->id,
                'response'   => $started,
            ]);

            if (empty($started['paymentId'])) {
                \DB::rollBack();
                \Log::error('barion.start.missing_payment_id', [
                    'payment_id' => $payment->id,
                    'response'   => $started,
                ]);
                return response()->json(['success' => false, 'message' => __('payment.barion_failed_missing_id')], 500);
            }

            \DB::table('payments')->where('id', $payment->id)->update([
                'barion_payment_id' => $started['paymentId'],
                'status'            => 'pending',
                'updated_at'        => now(),
            ]);

            \DB::commit();

            return response()->json([
                'success'      => true,
                'redirect_url' => $started['gatewayUrl'] ?? url('/admin/payments/index'),
            ]);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            \DB::rollBack();
            $body = $e->response ? ($e->response->json() ?? $e->response->body()) : 'N/A';
            \Log::error('barion.start.request_exception', [
                'payment_id' => $request->payment_id,
                'status'     => $e->response ? $e->response->status() : 'N/A',
                'body'       => $body,
            ]);
            return response()->json(['success' => false, 'message' => __('payment.barion_connection_error')], 500);
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('barion.start.throwable', [
                'payment_id' => $request->payment_id,
                'msg'        => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => __('payment.unknown_error')], 500);
        }
    }

    public function refresh(Request $request, \App\Services\BarionService $barion)
    {
        $barionId = $request->input('barion_payment_id');
        if (!$barionId) {
            return response()->json(['success' => false, 'message' => __('payment.missing_barion_id')], 400);
        }

        $payment = \DB::table('payments')->where('barion_payment_id', $barionId)->first();
        if (!$payment) {
            return response()->json(['success' => false, 'message' => __('payment.payment_not_found_short')], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['success' => true, 'status' => 'paid']);
        }
        if ($payment->status === 'failed') {
            return response()->json(['success' => true, 'status' => 'failed']);
        }

        try {
            $state  = $barion->getPaymentState($barionId);
            $status = strtoupper((string) ($state['Status'] ?? ''));

            \Log::info('barion.refresh.state', ['barion_id' => $barionId, 'status' => $status, 'raw' => $state]);

            if ($status === 'SUCCEEDED') {
                // Mark payment as paid and set invoice_status to 'pending'
                // Background job (ProcessPendingInvoices) will handle invoice creation
                \DB::table('payments')->where('id', $payment->id)->update([
                    'status'         => 'paid',
                    'paid_at'        => now(),
                    'invoice_status' => 'pending',
                    'updated_at'     => now(),
                ]);
                
                \Log::info('payment.refresh.marked_paid', [
                    'payment_id' => $payment->id,
                    'barion_id' => $barionId,
                    'invoice_status' => 'pending',
                ]);
                
                return response()->json(['success' => true, 'status' => 'paid']);
            }

            if (in_array($status, ['CANCELED','EXPIRED','FAILED'])) {
                \DB::table('payments')->where('id', $payment->id)->update([
                    'status'     => 'failed',
                    'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'status' => 'failed']);
            }

            // ✅ Still pending - just return status without changing anything
            return response()->json(['success' => true, 'status' => 'pending']);
        } catch (\Throwable $e) {
            \Log::error('barion.refresh.throwable', ['barion_id' => $barionId, 'msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Nem sikerült lekérdezni a fizetés állapotát.'], 500);
        }
    }

    public function invoice(Request $request, $id, \App\Services\BillingoService $billingo)
    {
        $orgId = (int) $request->session()->get('org_id');
        $p = \DB::table('payments')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->where('status', 'paid')
            ->first();

        if (!$p) {
            abort(404, __('payment.invoice_not_found'));
        }

        if (empty($p->billingo_document_id)) {
            abort(404, __('payment.invoice_not_issued'));
        }

        try {
            $pdf = $billingo->downloadInvoicePdf($p->billingo_document_id);
            $filename = $p->billingo_invoice_number ? ($p->billingo_invoice_number . '.pdf') : 'invoice.pdf';
            return response($pdf, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if ($e->response && $e->response->status() === 429) {
                abort(429, __('payment.too_many_requests'));
            }

            \Log::error('invoice.download.error', [
                'payment_id'  => $id,
                'document_id' => $p->billingo_document_id,
                'error'       => $e->getMessage(),
            ]);
            abort(500, __('payment.invoice_download_error'));
        } catch (\Throwable $e) {
            \Log::error('invoice.download.exception', [
                'payment_id'  => $id,
                'document_id' => $p->billingo_document_id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
            abort(500, __('payment.invoice_download_error_short'));
        }
    }

    public function getBillingData(Request $request)
    {
        $orgId = (int) $request->session()->get('org_id');
        
        // Get organization name
        $organization = \DB::table('organization')
            ->where('id', $orgId)
            ->whereNull('removed_at')
            ->first();
        
        // Get organization profile
        $profile = \DB::table('organization_profiles')
            ->where('organization_id', $orgId)
            ->first();
        
        return response()->json([
            'success' => true,
            'data' => [
                'organization_name' => $organization->name ?? '',
                'profile' => $profile ? (array) $profile : []
            ]
        ]);
    }

    /**
     * Save billing data for the organization
     */
    public function saveBillingData(Request $request)
    {
        $orgId = (int) $request->session()->get('org_id');
        
        // Validate input
        $request->validate([
            'company_name' => 'required|string|max:255',
            'tax_number' => 'required|string|max:191',
            'eu_vat_number' => 'nullable|string|max:32',
            'country_code' => 'required|string|size:2',
            'postal_code' => 'nullable|string|max:16',
            'city' => 'nullable|string|max:64',
            'region' => 'nullable|string|max:64',
            'street' => 'nullable|string|max:128',
            'house_number' => 'nullable|string|max:32',
            'phone' => 'nullable|string|max:32',
        ]);
        
        try {
            \DB::beginTransaction();
            
            // Update organization name
            \DB::table('organization')
                ->where('id', $orgId)
                ->update([
                    'name' => $request->company_name,
                ]);
            
            // Check if profile exists
            $profileExists = \DB::table('organization_profiles')
                ->where('organization_id', $orgId)
                ->exists();
            
            $profileData = [
                'tax_number' => $request->tax_number,
                'eu_vat_number' => $request->eu_vat_number,
                'country_code' => $request->country_code,
                'postal_code' => $request->postal_code,
                'city' => $request->city,
                'region' => $request->region,
                'street' => $request->street,
                'house_number' => $request->house_number,
                'phone' => $request->phone,
                'updated_at' => now()
            ];
            
            if ($profileExists) {
                // Update existing profile
                \DB::table('organization_profiles')
                    ->where('organization_id', $orgId)
                    ->update($profileData);
            } else {
                // Create new profile
                $profileData['organization_id'] = $orgId;
                $profileData['created_at'] = now();
                \DB::table('organization_profiles')->insert($profileData);
            }
            
            \DB::commit();
            
            \Log::info('billing.data.saved', [
                'org_id' => $orgId,
                'company_name' => $request->company_name
            ]);
            
            return response()->json([
                'success' => true,
                'message' => __('payment.billing_data.save_success')
            ]);
            
        } catch (\Throwable $e) {
            \DB::rollBack();
            
            \Log::error('billing.data.save.error', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => __('payment.billing_data.save_error')
            ], 500);
        }
    }
}