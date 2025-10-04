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

    // A blade "Nyitott tartozások" + "Korábban rendezettek" elrendezést vár:
    // Include 'initial' status in open payments
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

        // Sor zárolása versenyhelyzet ellen
        $payment = \DB::table('payments')->where('id', $request->payment_id)->lockForUpdate()->first();
        if (!$payment) {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Fizetési tétel nem található.'], 404);
        }

        if ($payment->status === 'paid') {
            \DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Ez a tétel már rendezett.'], 422);
        }

        // Ha már van folyamatban lévő Barion tranzakció, NE indítsunk újat
        if (!empty($payment->barion_payment_id) && $payment->status === 'pending') {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Ehhez a tételhez már folyamatban van egy fizetés. Kérlek, használd a visszatérési oldalt vagy frissítsd a státuszt.'
            ], 409);
        }

        // Összeg (HUF)
        $totalHuf = (int) ($payment->amount_huf ?? 0);
        if ($totalHuf <= 0) {
            $empCount = \DB::table('organization_user')
                ->where('organization_id', $payment->organization_id)
                ->where('role', 'employee')
                ->count();
            $totalHuf = max(1, $empCount) * 950;

            \DB::table('payments')->where('id', $payment->id)->update([
                'amount_huf' => $totalHuf,
                'updated_at' => now(),
            ]);
        }

        $quantity = max(1, (int) ceil($totalHuf / 950));

        // Admin e-mail
        $adminEmail = \DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $payment->organization_id)
            ->where('ou.role', 'admin')
            ->value('u.email');

        // Different comment for initial vs assessment payments
        if ($payment->status === 'initial') {
            $comment = '360° értékelés – Kezdeti regisztrációs díj';
        } else {
            $comment = '360° értékelés – mérés #' . $payment->assessment_id;
        }
        
        $payloadId = 'PAY-' . $payment->id;

        \Log::info('barion.start.init', [
            'payment_id'   => $payment->id,
            'org_id'       => $payment->organization_id,
            'assessment_id'=> $payment->assessment_id,
            'status'       => $payment->status,
            'total_huf'    => $totalHuf,
            'quantity'     => $quantity,
            'admin_email'  => $adminEmail,
        ]);

        $started = $barion->startPayment(
            paymentRequestId: $payloadId,
            totalHuf:         $totalHuf,
            quantity:         $quantity,
            comment:          $comment,
            payerHintEmail:   $adminEmail
        );

        \Log::info('barion.start.ok', [
            'payment_id'        => $payment->id,
            'barion_payment_id' => $started['paymentId'] ?? null,
            'gateway_url'       => $started['gatewayUrl'] ?? null,
        ]);

        // Állapot mentése - change from 'initial' to 'pending'
        \DB::table('payments')->where('id', $payment->id)->update([
            'barion_payment_id' => $started['paymentId'] ?? null,
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
    public function invoice(Request $request, $id)
    {
        $orgId = (int) $request->session()->get('org_id');
        $p = \DB::table('payments')
            ->where('id', $id)
            ->where('organization_id', $orgId)
            ->where('status', 'paid')
            ->first();

        if (!$p || !$p->invoice_pdf_url) {
            abort(404, 'Számla nem található vagy még nem lett kiállítva.');
        }

        return redirect($p->invoice_pdf_url);
    }

    /**
     * Státusz frissítése Barion API lekérdezéssel.
     * Ha sikeres, akkor Billingo számlázás is.
     */
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

    // EARLY RETURN: ha a webhook már frissített, ne hívjuk a Bariont feleslegesen
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
                    $this->issueBillingoInvoice((array) $payment, $billingo);
                } catch (\Throwable $e) {
                    \Log::error('billingo.refresh.error', ['payment_id' => $payment->id, 'msg' => $e->getMessage()]);
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

    /**
     * Billingo számla kiállítása a payment-hez
     */
    private function issueBillingoInvoice(array $payment, BillingoService $billingo): void
    {
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

        $quantity = max(1, (int) ceil($payment['amount_huf'] / 950));
        $docId    = $billingo->createInvoice($partnerId, $quantity, $payment['amount_huf']);

        $invoiceData = $billingo->getInvoice($docId);

        \DB::table('payments')->where('id', $payment['id'])->update([
            'billingo_partner_id'    => $partnerId,
            'billingo_document_id'   => $docId,
            'billingo_invoice_number'=> $invoiceData['invoice_number'] ?? null,
            'billingo_issue_date'    => $invoiceData['fulfillment_date'] ?? now()->toDateString(),
            'invoice_pdf_url'        => $invoiceData['public_url'] ?? null,
            'updated_at'             => now(),
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