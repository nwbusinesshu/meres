<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BarionService;
use App\Services\BillingoService;

class PaymentWebhookController extends Controller
{
    public function barion(Request $request, BarionService $barion, BillingoService $billingo)
    {
        Log::info('webhook.barion.in', [
            'body'    => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        $barionId = $request->input('PaymentId')
                 ?: $request->input('paymentId')
                 ?: $request->input('Id');

        if (!$barionId) {
            Log::warning('webhook.barion.missing_payment_id');
            return response('OK', 200);
        }

        try {
            $state  = $barion->getPaymentState($barionId);
            $status = strtoupper((string) ($state['Status'] ?? ''));

            Log::info('webhook.barion.state', ['barion_id' => $barionId, 'status' => $status]);

            $payment = DB::table('payments')
                ->where('barion_payment_id', $barionId)
                ->first();

            if (!$payment) {
                Log::warning('webhook.barion.payment_not_found', ['barion_id' => $barionId]);
                return response('OK', 200);
            }

            if ($status === 'SUCCEEDED') {
                if ($payment->status !== 'paid') {
                    DB::table('payments')->where('id', $payment->id)->update([
                        'status'    => 'paid',
                        'paid_at'   => now(),
                        'updated_at'=> now(),
                    ]);

                    $hasDoc = DB::table('payments')->where('id', $payment->id)->value('billingo_document_id');
                    if (!$hasDoc) {
                        try {
                            $this->issueBillingoInvoiceWithRetry((array) $payment, $billingo);
                        } catch (\Throwable $e) {
                            Log::error('webhook.billingo.issue_error', [
                                'payment_id' => $payment->id,
                                'msg'        => $e->getMessage(),
                                'trace'      => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }
            }
            elseif (in_array($status, ['CANCELED', 'EXPIRED', 'FAILED'])) {
                DB::table('payments')->where('id', $payment->id)->update([
                    'status'     => 'failed',
                    'updated_at' => now(),
                ]);
            }

            return response('OK', 200);

        } catch (\Throwable $e) {
            Log::error('webhook.barion.error', [
                'barion_id' => $barionId,
                'msg'       => $e->getMessage(),
            ]);
            return response('OK', 200);
        }
    }

    private function issueBillingoInvoiceWithRetry(array $payment, BillingoService $billingo, int $maxRetries = 3): void
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                Log::info('webhook.billingo.invoice.attempt', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                    'max'        => $maxRetries,
                ]);
                
                $this->issueBillingoInvoice($payment, $billingo);
                
                Log::info('webhook.billingo.invoice.success', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                ]);
                
                return;
                
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning('webhook.billingo.invoice.retry', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                    'error'      => $e->getMessage(),
                ]);
                
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt));
                }
            }
        }
        
        Log::error('webhook.billingo.invoice.all_retries_failed', [
            'payment_id' => $payment['id'],
            'attempts'   => $maxRetries,
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown',
        ]);
        
        throw $lastException ?? new \Exception('Invoice creation failed after ' . $maxRetries . ' attempts');
    }

    private function issueBillingoInvoice(array $paymentArr, BillingoService $billingo): void
    {
        $orgId = (int) $paymentArr['organization_id'];
        $payId = (int) $paymentArr['id'];

        $org     = DB::table('organization')->where('id', $orgId)->first();
        $profile = DB::table('organization_profiles')->where('organization_id', $orgId)->first();

        if (!$org) {
            throw new \Exception('Organization not found: ' . $orgId);
        }

        $adminEmail = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', 'admin')
            ->value('u.email');

        Log::info('webhook.billingo.invoice.data', [
            'org_name'    => $org->name ?? 'N/A',
            'postal_code' => $profile->postal_code ?? 'missing',
            'city'        => $profile->city ?? 'missing',
            'admin_email' => $adminEmail ?? 'missing',
        ]);

        // Use findOrCreatePartner for consistency with AdminPaymentController
        $partnerId = $billingo->findOrCreatePartner([
            'name'         => $org->name,
            'country_code' => $profile->country_code ?? 'HU',
            'postal_code'  => $profile->postal_code,  // Will use default if empty
            'city'         => $profile->city,
            'address'      => trim(($profile->street ?? '') . ' ' . ($profile->house_number ?? '')),
            'tax_number'   => $profile->tax_number,
            'emails'       => array_filter([$adminEmail]),
        ]);

        Log::info('webhook.billingo.partner.resolved', [
            'payment_id' => $payId,
            'partner_id' => $partnerId,
        ]);

        $qty = max(1, (int) ceil(((int) ($paymentArr['amount_huf'] ?? 0)) / 950));
        
        $comment = '360° értékelés';
        if (!empty($paymentArr['assessment_id'])) {
            $comment .= ' – mérés #' . $paymentArr['assessment_id'];
        }
        $comment .= ' – ' . $org->name;

        $docId = $billingo->createInvoice(
            partnerId: $partnerId,
            quantity:  $qty,
            comment:   $comment,
            paid:      true
        );

        Log::info('webhook.billingo.invoice.created', [
            'payment_id'  => $payId,
            'document_id' => $docId,
        ]);

        // FIXED: Fetch complete invoice metadata
        $invoiceData = $billingo->getInvoiceWithMetadata($docId);

        Log::info('webhook.billingo.invoice.metadata_fetched', [
            'payment_id'     => $payId,
            'invoice_number' => $invoiceData['invoice_number'] ?? null,
            'public_url'     => $invoiceData['public_url'] ?? null,
        ]);

        // FIXED: Store ALL invoice metadata
        DB::table('payments')->where('id', $payId)->update([
            'billingo_partner_id'    => $partnerId,
            'billingo_document_id'   => $docId,
            'billingo_invoice_number'=> $invoiceData['invoice_number'] ?? null,
            'billingo_issue_date'    => $invoiceData['issue_date'] ?? $invoiceData['fulfillment_date'] ?? now()->toDateString(),
            'invoice_pdf_url'        => $invoiceData['public_url'] ?? null,
            'updated_at'             => now(),
        ]);

        Log::info('webhook.billingo.invoice.complete', [
            'payment_id'     => $payId,
            'document_id'    => $docId,
            'invoice_number' => $invoiceData['invoice_number'] ?? null,
        ]);
    }
}