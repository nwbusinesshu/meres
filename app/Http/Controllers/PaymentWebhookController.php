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
        // 1) Bemenet log
        Log::info('webhook.barion.in', [
            'body'    => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // 2) PaymentId több néven is jöhet
        $barionId = $request->input('PaymentId')
                 ?: $request->input('paymentId')
                 ?: $request->input('Id');

        if (!$barionId) {
            Log::warning('webhook.barion.missing_payment_id');
            // Mindig 200-zal válaszoljunk, különben jön a CallbackFailed e-mail
            return response('OK', 200);
        }

        try {
            // 3) Barion állapot lekérdezése (POSKey KELL!)
            $state  = $barion->getPaymentState($barionId);
            $status = strtoupper((string) ($state['Status'] ?? ''));

            Log::info('webhook.barion.state', ['barion_id' => $barionId, 'status' => $status]);

            // 4) Saját payment sor megkeresése
            $payment = DB::table('payments')
                ->where('barion_payment_id', $barionId)
                ->first();

            if (!$payment) {
                Log::warning('webhook.barion.payment_not_found', ['barion_id' => $barionId]);
                // Ilyenkor is 200 – a Barionnak elég, hogy fogadtuk
                return response('OK', 200);
            }

            if ($status === 'SUCCEEDED') {
                if ($payment->status !== 'paid') {
                    DB::table('payments')->where('id', $payment->id)->update([
                        'status'    => 'paid',
                        'paid_at'   => now(),
                        'updated_at'=> now(),
                    ]);

                    // Számla, ha még nincs
                    $hasDoc = DB::table('payments')->where('id', $payment->id)->value('billingo_document_id');
                    if (!$hasDoc) {
                        try {
                            $this->issueBillingoInvoice((array) $payment, $billingo);
                        } catch (\Throwable $e) {
                            Log::error('webhook.billingo.issue_error', [
                                'payment_id' => $payment->id,
                                'msg'        => $e->getMessage()
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

            // 5) A LEGFONTOSABB: mindig 200-zal zárjunk
            return response('OK', 200);

        } catch (\Throwable $e) {
            Log::error('webhook.barion.error', [
                'barion_id' => $barionId,
                'msg'       => $e->getMessage(),
            ]);
            // A Barion ne próbálgassa végtelenül → 200
            return response('OK', 200);
        }
    }

    /**
     * Billingo számla kiállítás – egyszerű, termék alapú sorral
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

        DB::table('payments')->where('id', $payId)->update([
            'billingo_document_id' => $docId,
            'updated_at'           => now(),
        ]);
    }
}
