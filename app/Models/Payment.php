<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    public $timestamps = true;

    protected $fillable = [
        'organization_id',
        'assessment_id',
        'currency',         // NEW: 'HUF' or 'EUR'
        'net_amount',       // NEW: Net amount before VAT
        'vat_rate',         // NEW: VAT rate (e.g., 0.27)
        'vat_amount',       // NEW: VAT amount
        'gross_amount',     // NEW: Total amount including VAT
        'status',
        'paid_at',
        'barion_payment_id',
        'barion_transaction_id',
        'billingo_partner_id',
        'billingo_document_id',
        'billingo_invoice_number',
        'billingo_issue_date',
        'invoice_pdf_url',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'currency' => 'string',
        'net_amount' => 'decimal:2',
        'vat_rate' => 'decimal:4',
        'vat_amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'billingo_issue_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Get formatted amount for display
     */
    public function getFormattedAmountAttribute(): string
    {
        return \App\Services\PaymentHelper::formatAmount($this->gross_amount, $this->currency);
    }
    
    /**
     * Check if payment is for Hungarian organization (has VAT)
     */
    public function hasVat(): bool
    {
        return $this->vat_rate > 0;
    }
}