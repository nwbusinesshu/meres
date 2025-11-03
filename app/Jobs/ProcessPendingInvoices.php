<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BillingoService;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;

class ProcessPendingInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 1; // Don't retry the job itself, we handle retries internally

    /**
     * Execute the job.
     * Processes all payments that need invoice creation.
     */
    public function handle()
    {
        Log::info('invoice.job.starting');

        try {
            $billingo = app(BillingoService::class);

            // Find all payments that need invoice processing
            $payments = DB::table('payments')
                ->where('status', 'paid')
                ->whereIn('invoice_status', ['pending', 'failed'])
                ->where('invoice_retry_count', '<', 5)
                ->where(function($query) {
                    // Either never tried, or last retry was long enough ago based on retry count
                    $query->whereNull('invoice_last_retry_at')
                        ->orWhere(function($q) {
                            // Retry delay logic: 0, 15min, 30min, 1hr, 2hr
                            $q->where('invoice_retry_count', '=', 0)
                              ->where('invoice_last_retry_at', '<', DB::raw('NOW()'));
                        })
                        ->orWhere(function($q) {
                            $q->where('invoice_retry_count', '=', 1)
                              ->where('invoice_last_retry_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 15 MINUTE)'));
                        })
                        ->orWhere(function($q) {
                            $q->where('invoice_retry_count', '=', 2)
                              ->where('invoice_last_retry_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 30 MINUTE)'));
                        })
                        ->orWhere(function($q) {
                            $q->where('invoice_retry_count', '=', 3)
                              ->where('invoice_last_retry_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 1 HOUR)'));
                        })
                        ->orWhere(function($q) {
                            $q->where('invoice_retry_count', '=', 4)
                              ->where('invoice_last_retry_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 2 HOUR)'));
                        });
                })
                ->orderBy('created_at', 'asc') // Process oldest first
                ->limit(20) // Process max 20 per run to avoid timeouts
                ->get();

            if ($payments->isEmpty()) {
                Log::info('invoice.job.no_pending_invoices');
                return;
            }

            Log::info('invoice.job.found_payments', [
                'count' => $payments->count()
            ]);

            $successCount = 0;
            $failureCount = 0;
            $ticketsCreated = 0;

            foreach ($payments as $payment) {
                try {
                    // Mark as processing
                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update([
                            'invoice_status' => 'processing',
                            'invoice_last_retry_at' => now(),
                            'updated_at' => now(),
                        ]);

                    Log::info('invoice.job.processing', [
                        'payment_id' => $payment->id,
                        'retry_count' => $payment->invoice_retry_count
                    ]);

                    // Try to create invoice
                    $this->createInvoiceForPayment($payment, $billingo);

                    // Success!
                    DB::table('payments')
                        ->where('id', $payment->id)
                        ->update([
                            'invoice_status' => 'issued',
                            'invoice_last_error' => null,
                            'updated_at' => now(),
                        ]);

                    Log::info('invoice.job.success', [
                        'payment_id' => $payment->id,
                        'retry_count' => $payment->invoice_retry_count
                    ]);

                    $successCount++;

                } catch (\Throwable $e) {
                    $newRetryCount = $payment->invoice_retry_count + 1;
                    $errorMessage = substr($e->getMessage(), 0, 1000); // Limit error length

                    Log::error('invoice.job.failed', [
                        'payment_id' => $payment->id,
                        'retry_count' => $payment->invoice_retry_count,
                        'new_retry_count' => $newRetryCount,
                        'error' => $errorMessage
                    ]);

                    // Check if this is the final attempt
                    if ($newRetryCount >= 5) {
                        // Mark as failed and create support ticket
                        DB::table('payments')
                            ->where('id', $payment->id)
                            ->update([
                                'invoice_status' => 'failed',
                                'invoice_retry_count' => $newRetryCount,
                                'invoice_last_error' => $errorMessage,
                                'updated_at' => now(),
                            ]);

                        // Create support ticket
                        try {
                            $this->createSupportTicket($payment, $errorMessage);
                            $ticketsCreated++;
                        } catch (\Throwable $ticketError) {
                            Log::error('invoice.job.ticket_creation_failed', [
                                'payment_id' => $payment->id,
                                'error' => $ticketError->getMessage()
                            ]);
                        }
                    } else {
                        // Still have retries left, mark as pending for next attempt
                        DB::table('payments')
                            ->where('id', $payment->id)
                            ->update([
                                'invoice_status' => 'pending',
                                'invoice_retry_count' => $newRetryCount,
                                'invoice_last_error' => $errorMessage,
                                'updated_at' => now(),
                            ]);
                    }

                    $failureCount++;
                }
            }

            Log::info('invoice.job.completed', [
                'total_processed' => $payments->count(),
                'successful' => $successCount,
                'failed' => $failureCount,
                'tickets_created' => $ticketsCreated
            ]);

        } catch (\Throwable $e) {
            Log::error('invoice.job.error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create invoice for a payment
     */
    private function createInvoiceForPayment($payment, BillingoService $billingo): void
    {
        $orgId = (int) $payment->organization_id;
        $paymentId = (int) $payment->id;

        $org = DB::table('organization')->where('id', $orgId)->first();
        if (!$org) {
            throw new \Exception("Organization not found: {$orgId}");
        }

        $profile = DB::table('organization_profiles')->where('organization_id', $orgId)->first();
        if (!$profile) {
            throw new \Exception("Organization profile not found for organization: {$orgId}");
        }

        // Get admin email
        $adminEmail = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', 'admin')
            ->value('u.email');

        // Sync partner (create or update)
        $partnerId = $billingo->syncPartner($orgId, [
            'name' => $org->name,
            'country_code' => $profile->country_code ?? 'HU',
            'postal_code' => $profile->postal_code,
            'city' => $profile->city,
            'address' => trim(($profile->street ?? '') . ' ' . ($profile->house_number ?? '')),
            'tax_number' => $profile->tax_number,
            'emails' => array_filter([$adminEmail]),
        ]);

        Log::info('invoice.job.partner_resolved', [
            'payment_id' => $paymentId,
            'partner_id' => $partnerId
        ]);

        // Get payment data
        $currency = $payment->currency ?? 'HUF';
        $grossAmount = (float) ($payment->gross_amount ?? 0);

        $comment = '360° értékelés';
        if (!empty($payment->assessment_id)) {
            $comment .= ' – mérés #' . $payment->assessment_id;
        }
        $comment .= ' – ' . $org->name;

        // Create invoice
        $docId = $billingo->createInvoiceWithAutoVat(
            partnerId: $partnerId,
            organizationId: $orgId,
            currency: $currency,
            grossAmount: $grossAmount,
            comment: $comment,
            paid: true
        );

        Log::info('invoice.job.invoice_created', [
            'payment_id' => $paymentId,
            'document_id' => $docId
        ]);

        // Get invoice metadata
        $invoiceData = $billingo->getInvoiceWithMetadata($docId);

        // Update payment record with invoice data
        DB::table('payments')->where('id', $paymentId)->update([
            'billingo_partner_id' => $partnerId,
            'billingo_document_id' => $docId,
            'billingo_invoice_number' => $invoiceData['invoice_number'] ?? null,
            'billingo_issue_date' => $invoiceData['issue_date'] ?? $invoiceData['fulfillment_date'] ?? now()->toDateString(),
            'invoice_pdf_url' => $invoiceData['public_url'] ?? null,
            'updated_at' => now(),
        ]);

        Log::info('invoice.job.payment_updated', [
            'payment_id' => $paymentId,
            'invoice_number' => $invoiceData['invoice_number'] ?? null
        ]);
    }

    /**
     * Create support ticket for failed invoice
     */
    private function createSupportTicket($payment, string $errorMessage): void
    {
        $orgId = (int) $payment->organization_id;

        // Get organization admin user
        $adminUser = DB::table('user as u')
            ->join('organization_user as ou', 'ou.user_id', '=', 'u.id')
            ->where('ou.organization_id', $orgId)
            ->where('ou.role', 'admin')
            ->select('u.id', 'u.email', 'u.name')
            ->first();

        if (!$adminUser) {
            Log::error('invoice.job.no_admin_found', [
                'payment_id' => $payment->id,
                'organization_id' => $orgId
            ]);
            throw new \Exception("No admin user found for organization: {$orgId}");
        }

        DB::beginTransaction();

        try {
            // Create ticket
            $ticket = SupportTicket::create([
                'user_id' => $adminUser->id,
                'organization_id' => $orgId,
                'title' => "Invoice Creation Failed - Payment #{$payment->id}",
                'priority' => 'high',
                'status' => 'open',
            ]);

            // Create initial message
            $messageContent = "Automatic invoice creation failed after 5 attempts.\n\n";
            $messageContent .= "**Payment Details:**\n";
            $messageContent .= "- Payment ID: {$payment->id}\n";
            $messageContent .= "- Amount: {$payment->gross_amount} {$payment->currency}\n";
            $messageContent .= "- Payment Date: {$payment->paid_at}\n";
            if (!empty($payment->assessment_id)) {
                $messageContent .= "- Assessment ID: {$payment->assessment_id}\n";
            }
            $messageContent .= "\n**Last Error:**\n";
            $messageContent .= $errorMessage;
            $messageContent .= "\n\n**Action Required:**\n";
            $messageContent .= "Please manually create the invoice for this payment or contact support.";

            SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $adminUser->id,
                'message' => $messageContent,
                'is_staff_reply' => false,
            ]);

            DB::commit();

            Log::info('invoice.job.ticket_created', [
                'payment_id' => $payment->id,
                'ticket_id' => $ticket->id,
                'admin_user_id' => $adminUser->id
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}