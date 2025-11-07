<?php

return [
    'title' => 'Billing',

    'sections' => [
        'open'    => 'Open Payments',
        'settled' => 'Invoices',
    ],

    'columns' => [
        'created_date'   => 'Date',
        'due_date'       => 'Due Date',
        'amount'         => 'Amount',
        'status'         => 'Status',
        'actions'        => 'Action',
        'issue_date'     => 'Issue Date',
        'payment_date'   => 'Payment Date',
        'invoice_number' => 'Invoice Number',
    ],

    'actions' => [
        'pay_now'          => 'Start Payment',
        'download_invoice' => 'Download Invoice',
    ],

    'status' => [
        'initial' => 'Initial',
        'pending' => 'In Progress',
        'failed'  => 'Failed',
        'paid'    => 'Settled',
    ],

    'invoice' => [
        'processing' => 'Processing',
        'downloading' => 'Downloading invoice',
    ],

    'empty' => [
        'open'    => 'Hooray! No outstanding payments.',
        'open-info' => 'Items awaiting payment will appear here!',
        'settled' => 'No previously settled items.',
        'info-settled' => 'Your invoices will appear here. Don\'t forget to set the exact billing information! We cannot modify an issued invoice!',
    ],

    'billing_data' => [
        'title'              => 'Billing Information',
        'button'             => 'Billing Information',
        'company_name'       => 'Company Name',
        'tax_number'         => 'Tax Number',
        'tax_number_hint'    => 'E.g.: 12345678-1-23',
        'eu_vat_number'      => 'EU VAT Number',
        'eu_vat_hint'        => 'Optional, e.g.: HU12345678',
        'country_code'       => 'Country',
        'postal_code'        => 'Postal Code',
        'city'               => 'City',
        'region'             => 'State/Region',
        'street'             => 'Street',
        'house_number'       => 'House Number',
        'phone'              => 'Phone Number',
        'load_error'         => 'Failed to load billing information.',
        'save_success'       => 'Billing information successfully saved.',
        'save_error'         => 'Failed to save billing information.',
    ],

    'swal' => [
        'paid_title'   => 'Payment Successful',
        'paid_text'    => 'Payment settled, invoice issued.',
        'failed_title' => 'Payment Failed',
        'failed_text'  => 'Payment was not completed.',

        'start_unknown_title' => 'Unknown Response',
        'start_unknown_text'  => 'Payment initiation returned an unclear response.',
        'start_fail_title'    => 'Failed to Initiate',
        'start_fail_text'     => 'Payment initiation failed.',

        // Blocked payment notification
        'payment_blocked_title' => 'Payment in Progress or Canceled',
        'payment_blocked_text' => 'The payment was not completed. Please wait 10 minutes before trying again.',

        // NEW: Connecting to Barion overlay
        'connecting_barion_title' => 'Connecting to Payment System...',
        'connecting_barion_text' => 'Please wait while we connect to the Barion payment system.',
        'connecting_barion_wait' => 'This may take a few seconds. Do not close this window!',

        // Redirect notification
        'redirecting_title' => 'Redirecting...',
        'redirecting_text' => 'Redirecting you to the Barion payment page.',
        
        // Initial payment blocking modal
        'initial_payment_required_title' => 'Initial Payment Required',
        'initial_payment_required_text' => 'To use the system, you must first settle the initial payment. Please initiate the payment using the button below.',
        'understand' => 'I Understand',

        // ✅ NEW translations for already paid detection
        'already_paid_title' => 'Payment Already Completed',
        'already_paid_text' => 'This payment has already been successfully completed in the Barion system.',
        
        // Make sure these exist:
        'payment_blocked_title' => 'Payment in Progress',
        'payment_blocked_text' => 'A payment is already in progress for this item. Please wait a few minutes or use the refresh button.',
        
        'connecting_barion_title' => 'Connecting to Barion...',
        'connecting_barion_text' => 'Payment transaction initiation in progress',
        'connecting_barion_wait' => 'Please do not close the window',
        
        'redirecting_title' => 'Redirecting...',
        'redirecting_text' => 'Redirecting to Barion payment page',
        
        'paid_title' => 'Payment Successful',
        'paid_text' => 'Payment was successfully completed.',
        
        'failed_title' => 'Payment Failed',
        'failed_text' => 'Payment failed or was canceled.',
        
        'start_unknown_title' => 'Unknown Response',
        'start_unknown_text' => 'Unexpected response received. Please try again.',
        
        'start_fail_title' => 'Payment Initiation Failed',
        'start_fail_text' => 'Failed to initiate payment. Please try again.',
    ],

    'trial' => [
        'active_title' => 'Trial Period Active',
        'active_message' => 'You are currently in the :days day trial period. Full system functionality is available, except for starting assessment periods.',
        'days_remaining' => ':days days remaining',
        'hours_remaining' => ':hours hours remaining',
        'expired_title' => 'Trial Period Expired',
        'expired_message' => 'The trial period has expired. To use the system, please settle the initial payment.',
        'pay_now' => 'Initiate Payment Now',
        'assessment_blocked' => 'Assessment period cannot be started before settling the initial payment.',
    ],

    // Update the initial payment blocking modal text
    'initial_payment_required_text' => 'To use the system, you must first settle the initial payment. During the 5-day trial period, you can add employees and configure the system, but you cannot start an assessment.',
    'trial-expired' => 'The trial period has expired. Please settle the first payment to use the system.',

    // BillingoService - Invoice Items
    'invoice-default-name' => 'QUARMA360 online performance assessment (per employee)',
    
    // BillingoService - Error Messages (if these bubble up to users)
    'eur-conversion-failed' => 'EUR invoice creation failed, no exchange rate available.',
    'conversion-rate-fetch-failed' => 'Failed to fetch exchange rate.',

    // Payment operations
    'payment_not_found' => 'Payment item not found.',
    'already_settled' => 'This item has already been settled.',
    'already_paid_reload' => 'This payment has already been successfully completed. The page will refresh...',
    'payment_in_progress' => 'A payment is already in progress for this item in the Barion system. Please use the return page or wait a few minutes.',
    'status_check_failed' => 'Failed to check payment status. Please try again or use the refresh button.',
    'invalid_amount' => 'Invalid amount.',
    'barion_failed_missing_id' => 'Barion payment failed (missing PaymentId).',
    'barion_connection_error' => 'Barion connection error.',
    'unknown_error' => 'An unknown error occurred.',
    'missing_barion_id' => 'Missing barion_payment_id',
    'payment_not_found_short' => 'Payment not found',
    'status_query_failed' => 'Failed to query payment status.',
    
    // Invoice
    'invoice_not_found' => 'Invoice not found.',
    'invoice_not_issued' => 'Invoice has not been issued yet.',
    'too_many_requests' => 'Too many requests. Please try again in a few minutes.',
    'invoice_download_error' => 'An error occurred while downloading the invoice. Please try again later.',
    'invoice_download_error_short' => 'An error occurred while downloading the invoice.',    
];