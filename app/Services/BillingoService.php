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
        $this->apiKey        = (string) env('BILLINGO_API_KEY');
        $this->blockId       = (int) env('BILLINGO_BLOCK_ID');
        $this->bankAccountId = (int) env('BILLINGO_BANK_ACCOUNT_ID');
        $this->productId     = (int) env('BILLINGO_PRODUCT_ID');
        $this->vatRate       = (int) env('BILLINGO_VAT_RATE', 27);
        $this->headers = [
            'X-API-KEY'    => env('BILLINGO_API_KEY', ''),
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
     * Partner létrehozása (vagy később ide tehetünk keresést és upsertet is)
     * Swagger minta: PartnerUpsert (name, address{country_code,post_code,city,address}, emails[], taxcode) 
     */
    public function createPartner(array $partner): int
    {
        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/partners', $partner)
            ->throw();

        return (int) $res->json('id');
    }

    /**
     * Számla létrehozása (DocumentInsert) – product_id + quantity alapján
     * Kötelező: partner_id, block_id, bank_account_id; példa a Billingo Swagger mintában. 
     */
    public function createInvoice(int $partnerId, int $quantity, string $comment = '', bool $paid = true): int
    {
        $today = now()->format('Y-m-d');

        $body = [
            'partner_id'      => $partnerId,
            'block_id'        => $this->blockId,
            'bank_account_id' => $this->bankAccountId,
            'type'            => 'invoice',      // vagy 'advance' – mi sima számlát kérünk
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

        $res = Http::withHeaders($this->headers())
            ->post($this->base . '/documents', $body)
            ->throw();

        return (int) $res->json('id'); // a létrejött dokumentum azonosítója
    }

    /**
     * Számla PDF letöltése és visszaadása binárisan.
     * (A v3-ban elérhető a /documents/{id}/download végpont.)
     */

    public function getPublicUrl(int $documentId): ?string
{
    $res = \Illuminate\Support\Facades\Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept'    => 'application/json',
        ])->get($this->base . '/v3/documents/' . $documentId . '/public-url')
          ->throw()
          ->json();

    return $res['public_url'] ?? null;
}

    /**
     * PDF letöltés közvetlenül a /documents/{id}/download végpontról.
     * 200 → PDF; 404 → még nem kész; egyéb → hiba
     */
    public function downloadInvoicePdf(int $documentId): string
    {
        $url = $this->base . "/documents/{$documentId}/download";

        $res = Http::withHeaders($this->headers)
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
     * Számla meta lekérés a /documents/{id}-ről.
     */
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

    /**
     * Dokumentum meta kinyerése: számlaszám + kiállítás dátum.
     */
    public function extractInvoiceMeta(array $doc): array
    {
        $number = $doc['invoice_number'] ?? $doc['document_number'] ?? null;
        // Billingo v3-nál több elnevezés is előfordult már → próbáljuk sorban
        $issue  = $doc['issue_date'] ?? $doc['invoice_date'] ?? $doc['fulfillment_date'] ?? null;

        return [
            'number'    => $number,
            'issueDate' => $issue,
        ];
    }
}

