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
    $open = \DB::table('payments as p')
        ->leftJoin('assessment as a', 'a.id', '=', 'p.assessment_id')
        ->where('p.organization_id', $orgId)
        ->whereIn('p.status', ['pending','failed'])
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

        $comment   = '360° értékelés – mérés #' . $payment->assessment_id;
        $payloadId = 'PAY-' . $payment->id;

        \Log::info('barion.start.init', [
            'payment_id'   => $payment->id,
            'org_id'       => $payment->organization_id,
            'assessment_id'=> $payment->assessment_id,
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

        // Állapot mentése
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
        $body = $e->response ? ($e->response->json() ?? $e->response->body()) : null;
        \Log::error('barion.start.http_error', [
            'payment_id' => $request->payment_id,
            'message'    => $e->getMessage(),
            'response'   => $body,
        ]);
        return response()->json([
            'success' => false,
            'message' => 'A Barion válasza hibát jelzett. Kérlek, próbáld újra később.',
        ], 502);
    } catch (\Throwable $e) {
        \DB::rollBack();
        \Log::error('barion.start.throwable', [
            'payment_id' => $request->payment_id,
            'message'    => $e->getMessage(),
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Váratlan hiba történt a fizetés indításakor.',
        ], 500);
    }
}


    /**
     * Számla (PDF) letöltése – a paymenthez kapcsolt Billingo dokumentumból
     */
   public function invoice(Request $request, int $id, \App\Services\BillingoService $billingo)
{
    // Eloquent-tel töltjük, ez nálad eddig is stabilan ment
    $payment = \App\Models\Payment::findOrFail($id);

    if (empty($payment->billingo_document_id)) {
        return back()->with('error', 'Ehhez a fizetéshez még nincs számla.');
    }

    try {
        $pdf = $billingo->downloadInvoicePdf((int) $payment->billingo_document_id);

        $filename = 'szamla-' . $payment->id . '.pdf';
        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);

    } catch (\Throwable $e) {
        // Ha a PDF még nem kész, adjunk felhasználóbarát üzenetet, ne dobjunk hibát
        \Log::warning('billingo.invoice.not_ready', [
            'payment_id' => $payment->id,
            'doc_id'     => $payment->billingo_document_id,
            'msg'        => $e->getMessage(),
        ]);
        return back()->with('error', 'A számla PDF még feldolgozás alatt áll. Kérlek, próbáld újra pár másodperc múlva.');
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
 * Billingo számla kiállítása – ugyanaz a logika, mint a webhookban
 * $paymentArr: DB-ből kiolvasott payments sor (array-ként)
 */
    private function issueBillingoInvoice(array $paymentArr, BillingoService $billingo): void
    {
        $orgId = (int) $paymentArr['organization_id'];
        $payId = (int) $paymentArr['id'];

        $org     = DB::table('organization')->where('id', $orgId)->first();
        $profile = DB::table('organization_profiles')->where('organization_id', $orgId)->first();

        // Admin e-mail partnerhez
        $adminEmail = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', 'admin')
            ->value('u.email');

        // Partner létrehozása/frissítése
        $partnerPayload = [
            'name'    => $org->name,
            'address' => [
                'country_code' => $profile->country_code ?? 'HU',
                'post_code'    => $profile->postal_code ?? '',
                'city'         => $profile->city ?? '',
                'address'      => trim(($profile->street ?? '') . ' ' . ($profile->house_number ?? '')),
            ],
            'emails'  => array_filter([$adminEmail]),
            'taxcode' => $profile->tax_number ?: ($profile->eu_vat_number ?? ''),
        ];

        $partnerId = $billingo->createPartner($partnerPayload);

        // Tétel mennyisége a 950 Ft egységárból
        $qty = max(1, (int) ceil(((int) ($paymentArr['amount_huf'] ?? 0)) / 950));

        $docId = $billingo->createInvoice(
            partnerId: $partnerId,
            quantity:  $qty,
            comment:   '360° értékelés – mérés #'.$paymentArr['assessment_id'].' – '.$org->name,
            paid:      true
        );

        $doc  = $billingo->getDocument((int) $docId);
        $meta = $billingo->extractInvoiceMeta($doc);

        $invoiceNumber = $meta['number'] ?? null;
        $issueDate     = $meta['issueDate'] ?? null;
        $issueDate     = $issueDate ? substr($issueDate, 0, 10) : null;

        DB::table('payments')->where('id', $payId)->update([
            'billingo_document_id'    => $docId,
            'billingo_invoice_number' => $invoiceNumber,
            'billingo_issue_date'     => $issueDate,
            'updated_at'              => now(),
        ]);
    }


}
