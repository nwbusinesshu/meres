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

        // Set locale based on currency: en-US for EUR, hu-HU for HUF
        $locale = ($currency === 'EUR') ? 'en-US' : 'hu-HU';

        $payload = [
            'POSKey'           => $this->posKey,
            'PaymentType'      => 'Immediate',
            'GuestCheckOut'    => true,
            'FundingSources'   => ['All'],
            'PaymentRequestId' => $paymentRequestId,
            'PayerHint'        => $payerHintEmail,
            'Locale'           => $locale,
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
            'locale' => $locale,
            'gross_amount' => $grossAmount,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'formatted_total' => $total,
        ]);

        // Convert payload to JSON to calculate content length
        $jsonPayload = json_encode($payload);
        $contentLength = strlen($jsonPayload);

        try {
            // Make request with retry logic, extended timeout, and explicit headers
            $res = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Content-Length' => (string) $contentLength,
            ])
            ->timeout(60)  // Increased timeout to 60 seconds
            ->retry(3, 1000, function ($exception, $request) {
                // Retry on connection errors, but not on 4xx client errors
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    \Log::warning('barion.payment.retry', [
                        'reason' => 'connection_timeout',
                        'message' => $exception->getMessage(),
                    ]);
                    return true;
                }
                return false;
            })
            ->post($this->base . '/v2/Payment/Start', $payload);

            // Log response for debugging
            \Log::info('barion.payment.response', [
                'payment_request_id' => $paymentRequestId,
                'status_code' => $res->status(),
                'has_payment_id' => !empty($res->json('PaymentId')),
                'has_gateway_url' => !empty($res->json('GatewayUrl')),
            ]);

            // Check for Barion API errors (even with 200 status)
            $responseData = $res->json();
            if (!empty($responseData['Errors'])) {
                \Log::error('barion.payment.api_errors', [
                    'payment_request_id' => $paymentRequestId,
                    'errors' => $responseData['Errors'],
                ]);
                
                // Throw exception with Barion error details
                $errorMessages = array_map(function($error) {
                    return ($error['ErrorCode'] ?? 'Unknown') . ': ' . ($error['Description'] ?? $error['Title'] ?? 'No description');
                }, $responseData['Errors']);
                
                throw new \Exception('Barion API errors: ' . implode('; ', $errorMessages));
            }

            // Check HTTP status
            if (!$res->successful()) {
                \Log::error('barion.payment.http_error', [
                    'payment_request_id' => $paymentRequestId,
                    'status_code' => $res->status(),
                    'body' => $res->body(),
                ]);
                $res->throw();
            }

            return [
                'paymentId'  => $res->json('PaymentId'),
                'gatewayUrl' => $res->json('GatewayUrl'),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Connection/timeout errors
            \Log::error('barion.payment.connection_error', [
                'payment_request_id' => $paymentRequestId,
                'error' => $e->getMessage(),
                'timeout' => '60s',
            ]);
            throw new \Exception('Barion connection timeout. Please try again.', 0, $e);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // HTTP errors (4xx, 5xx)
            \Log::error('barion.payment.request_error', [
                'payment_request_id' => $paymentRequestId,
                'status_code' => $e->response ? $e->response->status() : null,
                'body' => $e->response ? $e->response->body() : null,
            ]);
            throw $e;
        } catch (\Exception $e) {
            // Other errors
            \Log::error('barion.payment.general_error', [
                'payment_request_id' => $paymentRequestId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get payment state by PaymentId
     * Docs: GET /v2/Payment/GetPaymentState?PaymentId=...
     */
    public function getPaymentState(string $paymentId): array
    {
        try {
            // V2: GET + query string with POSKey
            $res = Http::timeout(30)
                ->retry(2, 500)
                ->get($this->base . '/v2/Payment/GetPaymentState', [
                    'PaymentId' => $paymentId,
                    'POSKey'    => $this->posKey,
                ]);

            // Check for Barion API errors
            $responseData = $res->json();
            if (!empty($responseData['Errors'])) {
                \Log::error('barion.getstate.api_errors', [
                    'payment_id' => $paymentId,
                    'errors' => $responseData['Errors'],
                ]);
                
                $errorMessages = array_map(function($error) {
                    return ($error['ErrorCode'] ?? 'Unknown') . ': ' . ($error['Description'] ?? $error['Title'] ?? 'No description');
                }, $responseData['Errors']);
                
                throw new \Exception('Barion API errors: ' . implode('; ', $errorMessages));
            }

            // Check HTTP status
            if (!$res->successful()) {
                \Log::error('barion.getstate.http_error', [
                    'payment_id' => $paymentId,
                    'status_code' => $res->status(),
                    'body' => $res->body(),
                ]);
                $res->throw();
            }

            return $responseData;

        } catch (\Exception $e) {
            \Log::error('barion.getstate.error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}