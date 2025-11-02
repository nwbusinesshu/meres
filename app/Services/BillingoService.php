<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BillingoService
{
    private string $base;
    private string $apiKey;
    private int $blockId;
    private int $bankAccountId;
    private int $productId;
    private array $headers;

    public function __construct()
    {
        $this->base          = rtrim(env('BILLINGO_API_URL', 'https://api.billingo.hu/v3'), '/');
        $this->apiKey        = (string) config('services.billingo.api_key');
        $this->blockId       = (int) config('services.billingo.block_id');
        $this->bankAccountId = (int) config('services.billingo.bank_account_id');
        $this->productId     = (int) config('services.billingo.product_id');
        $this->headers = [
            'X-API-KEY'    => config('services.billingo.api_key', ''),
            'Content-Type' => 'application/json',
        ];
    }

    private function headers(): array
    {
        return [
            'Accept'      => 'application/json',
            'Content-Type'=> 'application/json',
            'X-API-KEY'   => $this->apiKey,
        ];
    }

    /**
     * Partner keresése név alapján
     */
    public function searchPartner(string $name): ?array
    {
        try {
            $res = Http::withHeaders($this->headers())
                ->get($this->base . '/partners', ['name' => $name]);

            if (!$res->successful()) {
                Log::warning('billingo.partner.search_failed', [
                    'name'   => $name,
                    'status' => $res->status(),
                ]);
                return null;
            }

            $data = $res->json('data', []);
            return !empty($data) ? $data[0] : null;
        } catch (\Throwable $e) {
            Log::error('billingo.partner.search_error', [
                'name' => $name,
                'msg'  => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Partner létrehozása vagy megkeresése
     */
    public function findOrCreatePartner(array $partnerData): int
    {
        // Normalize and validate data with defaults
        $payload = [
            'name'    => $partnerData['name'] ?? 'N/A',
            'address' => [
                'country_code' => $partnerData['country_code'] ?? 'HU',
                'post_code'    => !empty($partnerData['postal_code']) ? $partnerData['postal_code'] : '1000',
                'city'         => !empty($partnerData['city']) ? $partnerData['city'] : 'Budapest',
                'address'      => !empty($partnerData['address']) ? $partnerData['address'] : 'N/A',
            ],
            'emails'  => !empty($partnerData['emails']) ? array_values(array_filter($partnerData['emails'])) : [],
            'taxcode' => $partnerData['tax_number'] ?? '',
        ];

        Log::info('billingo.partner.payload', ['payload' => $payload]);

        // Try to find existing partner
        $existing = $this->searchPartner($payload['name']);
        if ($existing && isset($existing['id'])) {
            Log::info('billingo.partner.found', ['partner_id' => $existing['id'], 'name' => $payload['name']]);
            return (int) $existing['id'];
        }

        // Create new partner
        Log::info('billingo.partner.creating', ['name' => $payload['name']]);
        return $this->createPartner($payload);
    }

    /**
     * Partner létrehozása
     */
    public function createPartner(array $partner): int
    {
        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/partners', $partner)
            ->throw();

        $partnerId = (int) $res->json('id');
        Log::info('billingo.partner.created', ['partner_id' => $partnerId]);
        
        return $partnerId;
    }

    /**
     * Számla létrehozása multi-currency és VAT támogatással
     * 
     * @param int $partnerId Billingo partner ID
     * @param int $organizationId Our organization ID (to get VAT info)
     * @param string $currency 'HUF' or 'EUR'
     * @param float $netAmount Net amount before VAT
     * @param float $vatRate VAT rate (e.g., 0.27 for 27%)
     * @param float $grossAmount Total amount including VAT
     * @param int $quantity Number of items
     * @param string $comment Invoice comment
     * @param bool $paid Whether invoice is already paid
     * @return int Document ID
     */
    public function createInvoiceWithVat(
        int $partnerId,
        int $organizationId,
        string $currency,
        float $netAmount,
        float $vatRate,
        float $grossAmount,
        int $quantity,
        string $comment = '',
        bool $paid = true
    ): int {
        $today = now()->format('Y-m-d');
        
        // Get organization profile for VAT details
        $profile = DB::table('organization_profiles')
            ->where('organization_id', $organizationId)
            ->first();
        
        $isHungary = ($profile && strtoupper($profile->country_code) === 'HU');
        
        // Calculate unit price (net)
        $unitPriceNet = $quantity > 0 ? round($netAmount / $quantity, 2) : 0;
        
        // Build invoice body
        $body = [
            'partner_id'      => $partnerId,
            'block_id'        => $this->blockId,
            'bank_account_id' => $this->bankAccountId,
            'type'            => 'invoice',
            'fulfillment_date'=> $today,
            'due_date'        => $today,
            'payment_method'  => 'bankcard',
            'language'        => $isHungary ? 'hu' : 'en',
            'currency'        => $currency,
            'electronic'      => true,
            'paid'            => $paid,
            'comment'         => $comment,
        ];
        
        // Add conversion rate if not HUF
        if ($currency !== 'HUF') {
            // Billingo will fetch the daily rate automatically if not provided
            // We can let Billingo handle this
            $body['conversion_rate'] = 1; // Will be updated by Billingo
        }
        
        // Build invoice items
        if ($isHungary) {
            // Hungarian organization - include VAT
            $body['items'] = [[
                'name'            => '360° értékelés',
                'unit_price'      => $unitPriceNet,
                'unit_price_type' => 'net',
                'quantity'        => $quantity,
                'unit'            => 'db',
                'vat'             => ($vatRate * 100) . '%', // e.g., "27%"
            ]];
        } else {
            // Non-Hungarian EU organization - reverse charge (AAM)
            $body['items'] = [[
                'name'            => '360° assessment / értékelés',
                'unit_price'      => $unitPriceNet,
                'unit_price_type' => 'net',
                'quantity'        => $quantity,
                'unit'            => 'pcs',
                'vat'             => '0%',
                'entitlement'     => 'AAM', // Reverse charge
                'comment'         => 'Reverse charge - ' . ($profile->eu_vat_number ?? 'EU VAT'),
            ]];
        }

        Log::info('billingo.invoice.creating_with_vat', [
            'partner_id' => $partnerId,
            'organization_id' => $organizationId,
            'currency' => $currency,
            'net_amount' => $netAmount,
            'vat_rate' => $vatRate,
            'gross_amount' => $grossAmount,
            'quantity' => $quantity,
            'is_hungary' => $isHungary,
            'unit_price_net' => $unitPriceNet,
        ]);

        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/documents', $body)
            ->throw();

        $documentId = (int) $res->json('id');
        
        Log::info('billingo.invoice.created', [
            'document_id' => $documentId,
            'partner_id' => $partnerId,
            'currency' => $currency,
            'gross_amount' => $grossAmount,
        ]);

        return $documentId;
    }

    /**
     * Számla PDF letöltése
     * 
     * @param int $documentId
     * @return string PDF content as binary string
     * @throws \RuntimeException
     */
    public function downloadInvoicePdf(int $documentId): string
    {
        $url = $this->base . "/documents/{$documentId}/download";

        $res = Http::withHeaders($this->headers())
            ->accept('application/pdf')
            ->get($url);

        if ($res->status() === 200) {
            $ct = strtolower($res->header('Content-Type') ?? '');
            if (str_contains($ct, 'application/pdf')) {
                return $res->body();
            }
        }

        if ($res->status() === 404) {
            Log::warning('billingo.invoice.not_ready', [
                'doc_id' => $documentId,
                'msg'    => 'Billingo download failed: HTTP 404',
            ]);
            throw new \RuntimeException('not_ready');
        }

        Log::error('billingo.invoice.download_error', [
            'doc_id' => $documentId,
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);
        throw new \RuntimeException('download_failed');
    }

    /**
     * Számla adatainak lekérése
     */
    public function getDocument(int $documentId): array
    {
        $res = Http::withHeaders($this->headers())
            ->get($this->base . '/documents/' . $documentId)
            ->throw();

        return $res->json();
    }

    /**
     * Számla publikus letöltési URL
     */
    public function getPublicUrl(int $documentId): ?string
    {
        try {
            $res = Http::withHeaders($this->headers())
                ->get($this->base . '/documents/' . $documentId . '/public-url')
                ->throw();

            return $res->json('public_url');
        } catch (\Throwable $e) {
            Log::error('billingo.public_url.error', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Számla meta adatainak kinyerése
     */
    private function extractInvoiceMeta(array $doc): array
    {
        $number = $doc['invoice_number'] ?? $doc['document_number'] ?? null;
        $issue  = $doc['issue_date'] ?? $doc['invoice_date'] ?? $doc['fulfillment_date'] ?? null;

        return [
            'number'    => $number,
            'issueDate' => $issue,
        ];
    }

    /**
     * Számla teljes adatainak lekérése
     */
    public function getInvoiceWithMetadata(int $documentId): array
    {
        $doc = $this->getDocument($documentId);
        $meta = $this->extractInvoiceMeta($doc);
        $publicUrl = $this->getPublicUrl($documentId);

        return [
            'invoice_number' => $meta['number'],
            'issue_date'     => $meta['issueDate'],
            'fulfillment_date' => $doc['fulfillment_date'] ?? null,
            'public_url'     => $publicUrl,
            'document'       => $doc,
        ];
    }
}