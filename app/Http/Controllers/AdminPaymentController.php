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

class AdminPaymentController extends Controller
{
    /**
     * Fizetések oldal – nyitott és rendezett tételek
     */
    public function index(Request $request)
    {
        $orgId = (int) $request->session()->get('org_id');

        $open = \DB::table('payments as p')
            ->leftJoin('assessment as a', 'a.id', '=', 'p.assessment_id')
            ->where('p.organization_id', $orgId)
            ->whereIn('p.status', ['initial', 'pending','failed'])
            ->orderByDesc('p.created_at')
            ->select('p.*', 'a.started_at', 'a.due_at', 'a.closed_at')
            ->get();

        $settled = \DB::table('payments as p')
            ->leftJoin('assessment as a', 'a.id', '=', 'p.assessment_id')
            ->where('p.organization_id', $orgId)
            ->where('p.status', 'paid')
            ->orderByDesc('p.created_at')
            ->select('p.*', 'a.started_at', 'a.due_at', 'a.closed_at')
            ->get();

        return view('admin.payments', compact('open','settled'));
    }

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
                return response()->json(['success' => false, 'message' => 'Fizetési tétel nem található.'], 404);
            }

            if ($payment->status === 'paid') {
                \DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Ez a tétel már rendezett.'], 422);
            }

            if (!empty($payment->barion_payment_id) && $payment->status === 'pending') {
                \DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Ehhez a tételhez már folyamatban van egy fizetés. Kérlek, használd a visszatérési oldalt vagy frissítsd a státuszt.'
                ], 409);
            }

            $totalHuf = (int) ($payment->amount_huf ?? 0);
            if ($totalHuf < 1) {
                \DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Érvénytelen összeg.'], 422);
            }

            $quantity = max(1, (int) ceil($totalHuf / 950));
            $paymentRequestId = 'pay_' . $payment->id . '_' . time();
            
            $comment = '360° értékelés';
            if (!empty($payment->assessment_id)) {
                $comment .= ' – mérés #' . $payment->assessment_id;
            }

            $payerEmail = $request->session()->get('email', null);

            \Log::info('barion.start.calling', [
                'payment_id'         => $payment->id,
                'payment_request_id' => $paymentRequestId,
                'total_huf'          => $totalHuf,
                'quantity'           => $quantity,
            ]);

            $started = $barion->startPayment(
                $paymentRequestId,
                $totalHuf,
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
                return response()->json(['success' => false, 'message' => 'Barion fizetés sikertelen (hiányzó PaymentId).'], 500);
            }

            // REMOVED: barion_audit_log insert (table doesn't exist)
            // Just update the payment record
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
                'msg'        => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Barion hiba: ' . $e->getMessage()], 500);
        } catch (\Throwable $e) {
            \DB::rollBack();
            \Log::error('barion.start.throwable', [
                'payment_id' => $request->payment_id,
                'msg'        => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Hiba történt a fizetés indításakor. Kérlek, próbáld újra pár másodperc múlva.'], 500);
        }
    }

    /**
 * Számla letöltése PDF formában (már kiállított számlákhoz).
 */
public function invoice(Request $request, $id, BillingoService $billingo)
{
    $orgId = (int) $request->session()->get('org_id');
    $p = \DB::table('payments')
        ->where('id', $id)
        ->where('organization_id', $orgId)
        ->where('status', 'paid')
        ->first();

    if (!$p) {
        abort(404, 'Számla nem található.');
    }

    if (empty($p->billingo_document_id)) {
        abort(404, 'Számla még nem lett kiállítva.');
    }

    try {
        \Log::info('invoice.download.start', [
            'payment_id'  => $id,
            'document_id' => $p->billingo_document_id,
        ]);

        // Download PDF from Billingo
        $pdfContent = $billingo->downloadInvoicePdf((int) $p->billingo_document_id);

        \Log::info('invoice.download.success', [
            'payment_id'  => $id,
            'document_id' => $p->billingo_document_id,
            'size'        => strlen($pdfContent),
        ]);

        // Generate filename: invoice_number or payment_id
        $filename = 'szamla_';
        if (!empty($p->billingo_invoice_number)) {
            $filename .= preg_replace('/[^a-zA-Z0-9_-]/', '_', $p->billingo_invoice_number);
        } else {
            $filename .= $id;
        }
        $filename .= '.pdf';

        // Return PDF for download
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');

    } catch (\RuntimeException $e) {
        if ($e->getMessage() === 'not_ready') {
            \Log::warning('invoice.download.not_ready', [
                'payment_id'  => $id,
                'document_id' => $p->billingo_document_id,
            ]);
            abort(503, 'A számla PDF még nem áll készen. Kérjük, próbálja újra pár perc múlva.');
        }

        \Log::error('invoice.download.error', [
            'payment_id'  => $id,
            'document_id' => $p->billingo_document_id,
            'error'       => $e->getMessage(),
        ]);
        abort(500, 'Hiba történt a számla letöltése közben. Kérjük, próbálja újra később.');
    } catch (\Throwable $e) {
        \Log::error('invoice.download.exception', [
            'payment_id'  => $id,
            'document_id' => $p->billingo_document_id,
            'error'       => $e->getMessage(),
            'trace'       => $e->getTraceAsString(),
        ]);
        abort(500, 'Hiba történt a számla letöltése közben.');
    }
}

    public function refresh(Request $request, \App\Services\BarionService $barion, \App\Services\BillingoService $billingo)
    {
        \Log::info('payments.refresh.in', [
            'body'  => $request->all(),
            'query' => $request->query(),
            'url'   => $request->fullUrl(),
        ]);

        $barionId = $request->input('barion_payment_id')
                 ?: $request->input('paymentId')
                 ?: $request->input('PaymentId')
                 ?: $request->query('paymentId')
                 ?: $request->query('PaymentId')
                 ?: $request->query('Id');

        if (!$barionId && $request->filled('payment_id')) {
            $barionId = \DB::table('payments')->where('id', $request->payment_id)->value('barion_payment_id');
        }
        if (!$barionId) {
            return response()->json(['success' => false, 'message' => 'Hiányzó Barion azonosító.'], 422);
        }

        $payment = \DB::table('payments')->where('barion_payment_id', $barionId)->first();
        if (!$payment && $request->filled('payment_id')) {
            $payment = \DB::table('payments')->where('id', $request->payment_id)->first();
        }
        if (!$payment) {
            \Log::warning('payments.refresh.unknown_barion_id', ['barion_id' => $barionId]);
            return response()->json(['success' => false, 'message' => 'Ismeretlen Barion azonosító.'], 404);
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
                \DB::table('payments')->where('id', $payment->id)->update([
                    'status'    => 'paid',
                    'paid_at'   => now(),
                    'updated_at'=> now(),
                ]);

                $billId = \DB::table('payments')->where('id', $payment->id)->value('billingo_document_id');
                if (!$billId) {
                    try {
                        $this->issueBillingoInvoiceWithRetry((array) $payment, $billingo);
                    } catch (\Throwable $e) {
                        \Log::error('billingo.refresh.error', [
                            'payment_id' => $payment->id,
                            'msg'        => $e->getMessage(),
                            'trace'      => $e->getTraceAsString(),
                        ]);
                    }
                }
                return response()->json(['success' => true, 'status' => 'paid']);
            }

            if (in_array($status, ['CANCELED','EXPIRED','FAILED'])) {
                \DB::table('payments')->where('id', $payment->id)->update([
                    'status'     => 'failed',
                    'updated_at' => now(),
                ]);
                return response()->json(['success' => true, 'status' => 'failed']);
            }

            return response()->json(['success' => true, 'status' => 'pending']);
        } catch (\Throwable $e) {
            \Log::error('barion.refresh.throwable', ['barion_id' => $barionId, 'msg' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Nem sikerült lekérdezni a fizetés állapotát.'], 500);
        }
    }

    private function issueBillingoInvoiceWithRetry(array $payment, BillingoService $billingo, int $maxRetries = 3): void
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                \Log::info('billingo.invoice.attempt', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                    'max'        => $maxRetries,
                ]);
                
                $this->issueBillingoInvoice($payment, $billingo);
                
                \Log::info('billingo.invoice.success', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                ]);
                
                return;
                
            } catch (\Throwable $e) {
                $lastException = $e;
                \Log::warning('billingo.invoice.retry', [
                    'payment_id' => $payment['id'],
                    'attempt'    => $attempt,
                    'error'      => $e->getMessage(),
                ]);
                
                if ($attempt < $maxRetries) {
                    sleep(pow(2, $attempt));
                }
            }
        }
        
        \Log::error('billingo.invoice.all_retries_failed', [
            'payment_id' => $payment['id'],
            'attempts'   => $maxRetries,
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown',
        ]);
        
        throw $lastException ?? new \Exception('Invoice creation failed after ' . $maxRetries . ' attempts');
    }

    private function issueBillingoInvoice(array $payment, BillingoService $billingo): void
    {
        \Log::info('billingo.invoice.starting', ['payment_id' => $payment['id']]);

        $org = \DB::table('organization')->where('id', $payment['organization_id'])->first();
        if (!$org) {
            throw new \Exception('Organization not found: ' . $payment['organization_id']);
        }

        $profile = \DB::table('organization_profiles')->where('organization_id', $org->id)->first();
        if (!$profile) {
            throw new \Exception('Organization profile missing for org: ' . $org->id);
        }

        $partnerId = $billingo->findOrCreatePartner([
            'name'         => $org->name,
            'country_code' => $profile->country_code ?? 'HU',
            'postal_code'  => $profile->postal_code,
            'city'         => $profile->city,
            'address'      => trim(($profile->street ?? '') . ' ' . ($profile->house_number ?? '')),
            'tax_number'   => $profile->tax_number,
            'emails'       => [$this->getAdminEmail($org->id)],
        ]);

        \Log::info('billingo.partner.resolved', [
            'payment_id' => $payment['id'],
            'partner_id' => $partnerId,
        ]);

        $quantity = max(1, (int) ceil($payment['amount_huf'] / 950));
        
        $comment = '360° értékelés';
        if (!empty($payment['assessment_id'])) {
            $comment .= ' – mérés #' . $payment['assessment_id'];
        }
        $comment .= ' – ' . $org->name;
        
        $docId = $billingo->createInvoice($partnerId, $quantity, $comment, true);

        \Log::info('billingo.invoice.created', [
            'payment_id'  => $payment['id'],
            'document_id' => $docId,
        ]);

        $invoiceData = $billingo->getInvoiceWithMetadata($docId);

        \Log::info('billingo.invoice.metadata_fetched', [
            'payment_id'     => $payment['id'],
            'invoice_number' => $invoiceData['invoice_number'] ?? null,
            'public_url'     => $invoiceData['public_url'] ?? null,
        ]);

        \DB::table('payments')->where('id', $payment['id'])->update([
            'billingo_partner_id'    => $partnerId,
            'billingo_document_id'   => $docId,
            'billingo_invoice_number'=> $invoiceData['invoice_number'] ?? null,
            'billingo_issue_date'    => $invoiceData['issue_date'] ?? $invoiceData['fulfillment_date'] ?? now()->toDateString(),
            'invoice_pdf_url'        => $invoiceData['public_url'] ?? null,
            'updated_at'             => now(),
        ]);

        \Log::info('billingo.invoice.complete', [
            'payment_id'     => $payment['id'],
            'document_id'    => $docId,
            'invoice_number' => $invoiceData['invoice_number'] ?? null,
        ]);
    }

    private function getAdminEmail(int $orgId): string
    {
        $email = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', 'admin')
            ->value('u.email');

        return $email ?: 'info@example.com';
    }
}