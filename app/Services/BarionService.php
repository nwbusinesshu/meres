<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BarionService
{
    private string $base;
    private string $posKey;
    private string $payeeEmail;
    private string $redirectUrl;
    private string $callbackUrl;

    public function __construct()
    {
        $this->base        = rtrim(env('BARION_API_URL', 'https://api.test.barion.com'), '/');
        $this->posKey      = (string) config('services.barion.poskey');
        $this->payeeEmail  = (string) config('services.barion.payee_email');
        $this->redirectUrl = (string) config('services.barion.redirect_url');
        $this->callbackUrl = (string) config('services.barion.callback_url');
    }

    /**
     * Start payment with multi-currency support
     * 
     * @param string $paymentRequestId
     * @param string $currency 'HUF' or 'EUR'
     * @param float $grossAmount Total amount including VAT
     * @param float $unitPrice Price per unit
     * @param int $quantity Number of items
     * @param string $comment Payment comment
     * @param string|null $payerHintEmail Payer's email
     * @return array ['paymentId' => string, 'gatewayUrl' => string]
     */
    public function startPayment(
        string $paymentRequestId, 
        string $currency, 
        float $grossAmount, 
        float $unitPrice, 
        int $quantity, 
        string $comment, 
        ?string $payerHintEmail = null
    ): array {
        // Format amounts based on currency
        // HUF: no decimals, EUR: 2 decimals
        if ($currency === 'HUF') {
            $total = (int) round($grossAmount);
            $unitPriceFormatted = (int) round($unitPrice);
            $itemTotal = (int) round($unitPrice * $quantity);
        } else {
            $total = round($grossAmount, 2);
            $unitPriceFormatted = round($unitPrice, 2);
            $itemTotal = round($unitPrice * $quantity, 2);
        }

        $payload = [
            'POSKey'           => $this->posKey,
            'PaymentType'      => 'Immediate',
            'GuestCheckOut'    => true,
            'FundingSources'   => ['All'],
            'PaymentRequestId' => $paymentRequestId,
            'PayerHint'        => $payerHintEmail,
            'Locale'           => 'hu-HU',
            'Currency'         => $currency,
            'RedirectUrl'      => $this->redirectUrl,
            'CallbackUrl'      => $this->callbackUrl,
            'Transactions'     => [[
                'POSTransactionId' => 'T-' . $paymentRequestId,
                'Payee'            => $this->payeeEmail,
                'Total'            => $total,
                'Comment'          => $comment,
                'Items'            => [[
                    'Name'        => '360° értékelés',
                    'Description' => 'Értékelések darabszáma',
                    'Quantity'    => $quantity,
                    'Unit'        => 'db',
                    'UnitPrice'   => $unitPriceFormatted,
                    'ItemTotal'   => $itemTotal,
                ]],
            ]],
        ];

        \Log::info('barion.payment.request', [
            'payment_request_id' => $paymentRequestId,
            'currency' => $currency,
            'gross_amount' => $grossAmount,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'formatted_total' => $total,
        ]);

        $res = Http::withHeaders(['Accept' => 'application/json'])
            ->post($this->base . '/v2/Payment/Start', $payload)
            ->throw();

        return [
            'paymentId'  => $res->json('PaymentId'),
            'gatewayUrl' => $res->json('GatewayUrl'),
        ];
    }

    /**
     * Get payment state by PaymentId
     * Docs: GET /v2/Payment/GetPaymentState?PaymentId=...
     */
    public function getPaymentState(string $paymentId): array
    {
        // V2: GET + query string with POSKey
        $res = Http::get($this->base . '/v2/Payment/GetPaymentState', [
            'PaymentId' => $paymentId,
            'POSKey'    => $this->posKey,
        ])->throw();

        return $res->json();
    }
}