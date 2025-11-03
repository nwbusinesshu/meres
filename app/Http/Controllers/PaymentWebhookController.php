<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\BarionService;
use App\Services\BillingoService;

class PaymentWebhookController extends Controller
{
    public function barion(Request $request, BarionService $barion, BillingoService $billingo)
    {
        $requestStartTime = microtime(true);
        
        // Enhanced structured logging
        Log::info('webhook.barion.in', [
            'body'    => $request->all(),
            'headers' => $request->headers->all(),
            'ip'      => $request->ip(),
            'method'  => $request->method(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $barionId = $request->input('PaymentId')
                 ?: $request->input('paymentId')
                 ?: $request->input('Id');

        if (!$barionId) {
            Log::warning('webhook.barion.missing_payment_id', [
                'ip' => $request->ip(),
                'body' => $request->all(),
            ]);
            return response('OK', 200);
        }

        // ENHANCED VALIDATION: PaymentId format validation
        $validationResult = $this->validatePaymentId($barionId);
        if (!$validationResult['valid']) {
            Log::warning('webhook.barion.invalid_payment_id_format', [
                'payment_id' => $barionId,
                'reason' => $validationResult['reason'],
                'ip' => $request->ip(),
            ]);
            // Still return OK to not reveal validation logic to attacker
            return response('OK', 200);
        }

        // ENHANCED VALIDATION: Suspicious pattern detection
        $suspiciousPatterns = $this->detectSuspiciousPatterns($barionId, $request->ip());
        if (!empty($suspiciousPatterns)) {
            Log::warning('webhook.barion.suspicious_patterns_detected', [
                'payment_id' => $barionId,
                'patterns' => $suspiciousPatterns,
                'ip' => $request->ip(),
            ]);
            // Continue processing but flag for review
        }

        // IDEMPOTENCY CHECK - Prevent duplicate processing
        $eventSignature = $this->generateEventSignature($barionId, $request->ip());
        
        // Check if this event was already processed recently (within 24 hours)
        $existingEvent = DB::table('webhook_events')
            ->where('event_signature', $eventSignature)
            ->where('created_at', '>', now()->subHours(24))
            ->first();
        
        if ($existingEvent) {
            Log::info('webhook.barion.duplicate_detected', [
                'barion_id' => $barionId,
                'event_id' => $existingEvent->id,
                'status' => $existingEvent->status,
                'original_timestamp' => $existingEvent->created_at,
                'time_since_original' => now()->diffInSeconds($existingEvent->created_at) . 's',
            ]);
            
            // If already completed, return success
            if ($existingEvent->status === 'completed') {
                return response('OK', 200);
            }
            
            // If still processing (race condition), wait a bit and return
            if ($existingEvent->status === 'processing') {
                Log::warning('webhook.barion.already_processing', [
                    'barion_id' => $barionId,
                    'event_id' => $existingEvent->id,
                ]);
                return response('OK', 200);
            }
        }
        
        // Create webhook event record to track this request
        try {
            $webhookEventId = DB::table('webhook_events')->insertGetId([
                'event_type' => 'barion.payment',
                'external_id' => $barionId,
                'event_signature' => $eventSignature,
                'source_ip' => $request->ip(),
                'status' => 'processing',
                'request_data' => json_encode([
                    'body' => $request->all(),
                    'headers' => [
                        'user_agent' => $request->userAgent(),
                        'content_type' => $request->header('Content-Type'),
                    ],
                    'suspicious_patterns' => $suspiciousPatterns,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Duplicate signature (race condition) - another request is processing
            if ($e->getCode() === '23000') {
                Log::info('webhook.barion.race_condition_detected', [
                    'barion_id' => $barionId,
                    'error' => 'Duplicate event signature',
                ]);
                return response('OK', 200);
            }
            throw $e;
        }

        // Process the webhook
        try {
            $state  = $barion->getPaymentState($barionId);
            $status = strtoupper((string) ($state['Status'] ?? ''));

            Log::info('webhook.barion.state', [
                'barion_id' => $barionId,
                'status' => $status,
                'processing_time_ms' => round((microtime(true) - $requestStartTime) * 1000, 2),
            ]);

            $payment = DB::table('payments')
                ->where('barion_payment_id', $barionId)
                ->first();

            if (!$payment) {
                Log::warning('webhook.barion.payment_not_found', [
                    'barion_id' => $barionId,
                    'barion_status' => $status,
                ]);
                
                // Mark event as completed even though payment not found
                DB::table('webhook_events')->where('id', $webhookEventId)->update([
                    'status' => 'completed',
                    'response_data' => json_encode([
                        'result' => 'payment_not_found',
                        'barion_status' => $status,
                    ]),
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
                
                return response('OK', 200);
            }

            // ENHANCED VALIDATION: Check payment state consistency
            $stateValidation = $this->validatePaymentState($payment, $status);
            if (!$stateValidation['valid']) {
                Log::warning('webhook.barion.inconsistent_state', [
                    'payment_id' => $payment->id,
                    'barion_id' => $barionId,
                    'current_status' => $payment->status,
                    'barion_status' => $status,
                    'reason' => $stateValidation['reason'],
                ]);
            }

            if ($status === 'SUCCEEDED') {
                if ($payment->status !== 'paid') {
                    // Mark payment as paid and set invoice_status to 'pending'
                    // Background job (ProcessPendingInvoices) will handle invoice creation
                    DB::table('payments')->where('id', $payment->id)->update([
                        'status'           => 'paid',
                        'paid_at'          => now(),
                        'invoice_status'   => 'pending',
                        'updated_at'       => now(),
                    ]);

                    Log::info('webhook.barion.payment_marked_paid', [
                        'payment_id' => $payment->id,
                        'barion_id' => $barionId,
                        'gross_amount' => $payment->gross_amount,
                        'currency' => $payment->currency,
                        'organization_id' => $payment->organization_id,
                        'invoice_status' => 'pending',
                    ]);
                }
            }
            elseif (in_array($status, ['CANCELED', 'EXPIRED', 'FAILED'])) {
                DB::table('payments')->where('id', $payment->id)->update([
                    'status'     => 'failed',
                    'updated_at' => now(),
                ]);
                
                Log::info('webhook.barion.payment_marked_failed', [
                    'payment_id' => $payment->id,
                    'barion_id' => $barionId,
                    'barion_status' => $status,
                ]);
            }

            $processingTime = round((microtime(true) - $requestStartTime) * 1000, 2);

            // Mark webhook event as successfully completed
            DB::table('webhook_events')->where('id', $webhookEventId)->update([
                'status' => 'completed',
                'response_data' => json_encode([
                    'barion_status' => $status,
                    'payment_id' => $payment->id,
                    'payment_status' => $payment->status,
                    'processing_time_ms' => $processingTime,
                ]),
                'processed_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('webhook.barion.completed', [
                'barion_id' => $barionId,
                'payment_id' => $payment->id,
                'final_status' => $payment->status,
                'processing_time_ms' => $processingTime,
            ]);

            return response('OK', 200);

        } catch (\Throwable $e) {
            $processingTime = round((microtime(true) - $requestStartTime) * 1000, 2);
            
            Log::error('webhook.barion.error', [
                'barion_id' => $barionId,
                'msg'       => $e->getMessage(),
                'processing_time_ms' => $processingTime,
            ]);
            
            // Mark webhook event as failed
            DB::table('webhook_events')->where('id', $webhookEventId)->update([
                'status' => 'failed',
                'response_data' => json_encode([
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'processing_time_ms' => $processingTime,
                ]),
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
            
            return response('OK', 200);
        }
    }
    
    /**
     * Validate PaymentId format.
     * Barion PaymentIds are 32-character lowercase hexadecimal strings.
     * 
     * @param string $paymentId
     * @return array ['valid' => bool, 'reason' => string]
     */
    private function validatePaymentId(string $paymentId): array
    {
        // Check length
        if (strlen($paymentId) !== 32) {
            return [
                'valid' => false,
                'reason' => 'Invalid length: ' . strlen($paymentId) . ' (expected 32)'
            ];
        }
        
        // Check if hexadecimal
        if (!ctype_xdigit($paymentId)) {
            return [
                'valid' => false,
                'reason' => 'Contains non-hexadecimal characters'
            ];
        }
        
        // Check if lowercase (Barion uses lowercase)
        if ($paymentId !== strtolower($paymentId)) {
            return [
                'valid' => false,
                'reason' => 'Contains uppercase characters (expected lowercase)'
            ];
        }
        
        return ['valid' => true, 'reason' => null];
    }
    
    /**
     * Detect suspicious patterns in webhook requests.
     * 
     * @param string $paymentId
     * @param string $sourceIp
     * @return array List of detected suspicious patterns
     */
    private function detectSuspiciousPatterns(string $paymentId, string $sourceIp): array
    {
        $patterns = [];
        
        // Check for rapid repeated requests (within last 5 seconds)
        $recentCount = DB::table('webhook_events')
            ->where('external_id', $paymentId)
            ->where('source_ip', $sourceIp)
            ->where('created_at', '>', now()->subSeconds(5))
            ->count();
        
        if ($recentCount > 2) {
            $patterns[] = "rapid_requests_count_{$recentCount}_in_5s";
        }
        
        // Check for same payment from multiple IPs (potential attack)
        $uniqueIps = DB::table('webhook_events')
            ->where('external_id', $paymentId)
            ->where('created_at', '>', now()->subMinutes(5))
            ->distinct('source_ip')
            ->count('source_ip');
        
        if ($uniqueIps > 2) {
            $patterns[] = "multiple_ips_count_{$uniqueIps}_for_same_payment";
        }
        
        // Check for sequential test-looking PaymentIds
        if (preg_match('/test|demo|sample|dummy|fake/i', $paymentId)) {
            $patterns[] = 'test_payment_id_pattern';
        }
        
        return $patterns;
    }
    
    /**
     * Validate payment state consistency.
     * 
     * @param object $payment
     * @param string $barionStatus
     * @return array ['valid' => bool, 'reason' => string]
     */
    private function validatePaymentState($payment, string $barionStatus): array
    {
        // If payment is already paid, Barion should report SUCCEEDED
        if ($payment->status === 'paid' && $barionStatus !== 'SUCCEEDED') {
            return [
                'valid' => false,
                'reason' => "Payment marked paid but Barion reports {$barionStatus}"
            ];
        }
        
        // If Barion reports SUCCEEDED but we're receiving notification again
        // (This is actually handled by idempotency, but log it as suspicious)
        if ($payment->status === 'paid' && $barionStatus === 'SUCCEEDED') {
            return [
                'valid' => true,
                'reason' => 'Duplicate success notification (handled by idempotency)'
            ];
        }
        
        return ['valid' => true, 'reason' => null];
    }
    
    /**
     * Generate a unique signature for this webhook event.
     * Prevents duplicate processing of the same payment notification.
     * 
     * @param string $barionId
     * @param string $sourceIp
     * @return string
     */
    private function generateEventSignature(string $barionId, string $sourceIp): string
    {
        // Create signature based on PaymentId and source IP
        // Same payment from same IP within 24h = duplicate
        return hash('sha256', $barionId . '|' . $sourceIp);
    }
}