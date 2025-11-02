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

    // EU country codes for VAT handling
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 
        'FR', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK'
    ];

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
     * Manage Billingo partner for organization - create or update
     * Each organization has ONE Billingo partner that gets updated when billing data changes
     * 
     * @param int $organizationId Our organization ID
     * @param array $partnerData Partner information
     * @return int Billingo partner ID
     */
    public function syncPartner(int $organizationId, array $partnerData): int
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

        Log::info('billingo.partner.sync.start', [
            'organization_id' => $organizationId,
            'payload' => $payload
        ]);

        // Check if organization already has a Billingo partner
        $profile = DB::table('organization_profiles')
            ->where('organization_id', $organizationId)
            ->first();

        if ($profile && $profile->billingo_partner_id) {
            // UPDATE existing partner
            Log::info('billingo.partner.updating', [
                'organization_id' => $organizationId,
                'partner_id' => $profile->billingo_partner_id,
                'name' => $payload['name']
            ]);

            try {
                $this->updatePartner($profile->billingo_partner_id, $payload);
                
                Log::info('billingo.partner.updated', [
                    'organization_id' => $organizationId,
                    'partner_id' => $profile->billingo_partner_id
                ]);

                return (int) $profile->billingo_partner_id;

            } catch (\Throwable $e) {
                // If update fails (partner deleted in Billingo), create new one
                Log::warning('billingo.partner.update_failed', [
                    'organization_id' => $organizationId,
                    'old_partner_id' => $profile->billingo_partner_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // CREATE new partner
        Log::info('billingo.partner.creating', [
            'organization_id' => $organizationId,
            'name' => $payload['name'],
            'taxcode' => $payload['taxcode']
        ]);

        $partnerId = $this->createPartner($payload);

        // Save partner ID to organization profile
        DB::table('organization_profiles')
            ->where('organization_id', $organizationId)
            ->update([
                'billingo_partner_id' => $partnerId,
                'updated_at' => now()
            ]);

        Log::info('billingo.partner.created_and_saved', [
            'organization_id' => $organizationId,
            'partner_id' => $partnerId
        ]);

        return $partnerId;
    }

    /**
     * Create new partner in Billingo
     */
    public function createPartner(array $partner): int
    {
        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/partners', $partner)
            ->throw();

        $partnerId = (int) $res->json('id');
        Log::info('billingo.partner.api.created', ['partner_id' => $partnerId]);
        
        return $partnerId;
    }

    /**
     * Update existing partner in Billingo
     */
    public function updatePartner(int $partnerId, array $partner): void
    {
        Http::withHeaders($this->headers())
            ->put($this->base . '/partners/' . $partnerId, $partner)
            ->throw();

        Log::info('billingo.partner.api.updated', ['partner_id' => $partnerId]);
    }

    /**
     * Calculate VAT based on country
     * 
     * @param string $countryCode 2-letter country code
     * @return array ['rate' => float, 'key' => string] - rate is 0-1 decimal, key is for Billingo API
     */
    public function calculateVat(string $countryCode): array
    {
        $countryCode = strtoupper($countryCode);

        if ($countryCode === 'HU') {
            // Hungary: 27% VAT
            return [
                'rate' => 0.27,
                'key' => '27%'
            ];
        } elseif (in_array($countryCode, self::EU_COUNTRIES)) {
            // EU (non-HU): 0% VAT (reverse charge)
            return [
                'rate' => 0.0,
                'key' => 'EU'
            ];
        } else {
            // Outside EU: 0% VAT (export)
            return [
                'rate' => 0.0,
                'key' => 'TAM'  // Tax Amount (outside EU)
            ];
        }
    }

    /**
     * Create invoice with automatic VAT calculation
     * 
     * @param int $partnerId Billingo partner ID
     * @param int $organizationId Our organization ID (to get country)
     * @param string $currency 'HUF' or 'EUR'
     * @param float $netAmount Net amount (before VAT)
     * @param int $quantity Number of items
     * @param string $comment Invoice comment
     * @param bool $paid Whether invoice is already paid
     * @return int Document ID
     */
    public function createInvoiceWithAutoVat(
        int $partnerId,
        int $organizationId,
        string $currency,
        float $netAmount,
        int $quantity,
        string $comment = '',
        bool $paid = true
    ): int {
        $today = now()->format('Y-m-d');
        
        // Get organization profile for country code
        $profile = DB::table('organization_profiles')
            ->where('organization_id', $organizationId)
            ->first();
        
        $countryCode = $profile->country_code ?? 'HU';
        
        // Calculate VAT automatically
        $vat = $this->calculateVat($countryCode);
        $vatRate = $vat['rate'];
        $vatKey = $vat['key'];
        
        // Calculate amounts
        $grossAmount = $netAmount * (1 + $vatRate);
        $unitPriceNet = $quantity > 0 ? round($netAmount / $quantity, 2) : 0;
        
        // Format amounts based on currency
        if ($currency === 'HUF') {
            $unitPriceNet = (int) round($unitPriceNet);
            $netAmount = (int) round($netAmount);
            $grossAmount = (int) round($grossAmount);
        } else {
            $unitPriceNet = round($unitPriceNet, 2);
            $netAmount = round($netAmount, 2);
            $grossAmount = round($grossAmount, 2);
        }
        
        Log::info('billingo.invoice.creating', [
            'partner_id' => $partnerId,
            'organization_id' => $organizationId,
            'currency' => $currency,
            'country_code' => $countryCode,
            'net_amount' => $netAmount,
            'vat_rate' => $vatRate,
            'vat_key' => $vatKey,
            'gross_amount' => $grossAmount,
            'quantity' => $quantity,
            'unit_price_net' => $unitPriceNet,
        ]);

        $payload = [
            'partner_id' => $partnerId,
            'block_id' => $this->blockId,
            'bank_account_id' => $this->bankAccountId,
            'type' => 'invoice',
            'fulfillment_date' => $today,
            'due_date' => $today,
            'payment_method' => 'online_bankcard',
            'language' => 'hu',
            'currency' => $currency,
            'paid' => $paid,
            'items' => [[
                'name' => $comment ?: '360° értékelés',
                'unit_price' => $unitPriceNet,
                'unit_price_type' => 'net',
                'quantity' => $quantity,
                'unit' => 'db',
                'vat' => $vatKey,  // '27%', 'EU', or 'TAM'
                'product_id' => $this->productId,
            ]],
        ];

        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/documents', $payload)
            ->throw();

        $docId = (int) $res->json('id');
        
        Log::info('billingo.invoice.created', [
            'document_id' => $docId,
            'partner_id' => $partnerId,
            'currency' => $currency,
            'gross_amount' => $grossAmount,
            'vat_applied' => $vatKey
        ]);
        
        return $docId;
    }

    /**
     * Get document details
     */
    public function getDocument(int $documentId): array
    {
        $res = Http::withHeaders($this->headers())
            ->get($this->base . '/documents/' . $documentId)
            ->throw();

        return $res->json();
    }

    /**
     * Get public download URL for invoice
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
     * Extract invoice metadata
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
     * Get invoice with full metadata
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
}