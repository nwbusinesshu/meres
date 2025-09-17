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
        'amount_huf',
        'status',
        'paid_at',
        'barion_payment_id',
        'barion_transaction_id',
        'billingo_partner_id',
        'billingo_document_id',
        'billingo_invoice_number',
        'invoice_pdf_url',
        'created_at',
        'updated_at',
    ];

    protected $dates = [
        'paid_at',
        'created_at',
        'updated_at',
    ];
}
