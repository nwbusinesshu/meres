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
        $this->posKey      = (string) env('BARION_POSKEY');
        $this->payeeEmail  = (string) env('BARION_PAYEE_EMAIL');
        $this->redirectUrl = (string) env('BARION_REDIRECT_URL');
        $this->callbackUrl = (string) env('BARION_CALLBACK_URL');
    }

    /**
     * Start payment => returns [paymentId, gatewayUrl]
     * Docs: POST /v2/Payment/Start
     */
    public function startPayment(string $paymentRequestId, int $totalHuf, int $quantity, string $comment, ?string $payerHintEmail = null): array
    {
        $payload = [
            'POSKey'           => $this->posKey,
            'PaymentType'      => 'Immediate',
            'GuestCheckOut'    => true,
            'FundingSources'   => ['All'],
            'PaymentRequestId' => $paymentRequestId,             // idempotens kulcs
            'PayerHint'        => $payerHintEmail,
            'Locale'           => 'hu-HU',
            'Currency'         => 'HUF',
            'RedirectUrl'      => $this->redirectUrl,
            'CallbackUrl'      => $this->callbackUrl,
            'Transactions'     => [[
                'POSTransactionId' => 'T-' . $paymentRequestId,
                'Payee'            => $this->payeeEmail,
                'Total'            => $totalHuf,
                'Comment'          => $comment,
                'Items'            => [[
                    'Name'        => '360° értékelés',
                    'Description' => 'Értékelések darabszáma',
                    'Quantity'    => $quantity,
                    'Unit'        => 'db',
                    'UnitPrice'   => 950,
                    'ItemTotal'   => 950 * $quantity,
                ]],
            ]],
        ];

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
    // V2: GET + query string-ben kötelező a POSKey
    $res = \Illuminate\Support\Facades\Http::get($this->base . '/v2/Payment/GetPaymentState', [
        'PaymentId' => $paymentId,
        'POSKey'    => $this->posKey,   // <-- EZ HIÁNYZOTT
    ])->throw();

    return $res->json();
}

}
