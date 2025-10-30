<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingoService
{
    private string $base;
    private string $apiKey;
    private int $blockId;
    private int $bankAccountId;
    private int $productId;
    private int $vatRate;
    private array $headers;

    public function __construct()
    {
        $this->base          = rtrim(env('BILLINGO_API_URL', 'https://api.billingo.hu/v3'), '/');
        $this->apiKey        = (string) config('services.billingo.api_key');
        $this->blockId       = (int) config('services.billingo.block_id');
        $this->bankAccountId = (int) config('services.billingo.bank_account_id');
        $this->productId     = (int) config('services.billingo.product_id');
        $this->vatRate       = (int) config('services.billingo.vat_rate', 27);
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
                'post_code'    => !empty($partnerData['postal_code']) ? $partnerData['postal_code'] : '1000',  // Default Budapest
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
     * Számla létrehozása
     */
    public function createInvoice(int $partnerId, int $quantity, string $comment = '', bool $paid = true): int
    {
        $today = now()->format('Y-m-d');

        $body = [
            'partner_id'      => $partnerId,
            'block_id'        => $this->blockId,
            'bank_account_id' => $this->bankAccountId,
            'type'            => 'invoice',
            'fulfillment_date'=> $today,
            'due_date'        => $today,
            'payment_method'  => 'bankcard',
            'language'        => 'hu',
            'currency'        => 'HUF',
            'electronic'      => true,
            'paid'            => $paid,
            'items' => [[
                'product_id' => $this->productId,
                'quantity'   => $quantity,
            ]],
            'comment'         => $comment,
        ];

        Log::info('billingo.invoice.creating', [
            'partner_id' => $partnerId,
            'quantity'   => $quantity,
            'comment'    => $comment,
        ]);

        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/documents', $body)
            ->throw();

        $docId = (int) $res->json('id');
        Log::info('billingo.invoice.created', ['document_id' => $docId]);

        return $docId;
    }

    public function getPublicUrl(int $documentId): ?string
    {
        try {
            $res = Http::withHeaders([
                    'X-API-KEY' => $this->apiKey,
                    'Accept'    => 'application/json',
                ])
                ->get($this->base . '/documents/' . $documentId . '/public-url')
                ->throw()
                ->json();

            return $res['public_url'] ?? null;
        } catch (\Throwable $e) {
            Log::error('billingo.public_url.error', [
                'document_id' => $documentId,
                'msg'         => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function downloadInvoicePdf(int $documentId): string
{
    // FIXED: Correct URL path
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

    public function getDocument(int $documentId): array
    {
        $url = $this->base . "/documents/{$documentId}";

        $res = Http::withHeaders($this->headers)
            ->accept('application/json')
            ->get($url);

        if ($res->successful()) {
            return $res->json();
        }

        Log::error('billingo.document.fetch_error', [
            'doc_id' => $documentId,
            'status' => $res->status(),
            'body'   => $res->body(),
        ]);
        throw new \RuntimeException('document_fetch_failed');
    }

    public function extractInvoiceMeta(array $doc): array
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