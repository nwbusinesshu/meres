<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestBarionConnection extends Command
{
    protected $signature = 'test:barion {--production : Test production API instead of test}';
    protected $description = 'Test Barion API connectivity';

    public function handle()
    {
        $useProduction = $this->option('production');
        
        if ($useProduction) {
            $apiUrl = 'https://api.barion.com';
            $this->warn('⚠ Testing PRODUCTION API - may create real payment records!');
        } else {
            $apiUrl = 'https://api.test.barion.com';
            $this->info('Testing TEST API (safe)');
        }
        
        $posKey = config('services.barion.poskey');
        $payeeEmail = config('services.barion.payee_email');
        
        $this->newLine();
        $this->line("API URL: {$apiUrl}");
        $this->line("POS Key: " . substr($posKey, 0, 10) . '...');
        $this->newLine();

        // Test 1: Simple TCP connection
        $this->info('1. Testing TCP connection...');
        $host = parse_url($apiUrl, PHP_URL_HOST);
        $port = 443;
        $timeout = 5;
        
        $start = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        
        if ($socket) {
            fclose($socket);
            $this->line("   ✓ TCP connection successful ({$elapsed}ms)");
        } else {
            $this->error("   ✗ TCP connection failed: {$errstr} ({$errno})");
            return 1;
        }

        // Test 2: Simple GET request (no payload)
        $this->info('2. Testing simple GET request...');
        $start = microtime(true);
        
        try {
            $response = Http::timeout(10)->get($apiUrl);
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->line("   ✓ GET request completed ({$elapsed}ms) - Status: {$response->status()}");
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->error("   ✗ GET request failed ({$elapsed}ms): " . $e->getMessage());
        }

        // Test 3: Small POST request
        $this->info('3. Testing small POST request...');
        $start = microtime(true);
        
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl . '/v2/Payment/GetPaymentState', [
                    'POSKey' => $posKey,
                    'PaymentId' => 'test123'
                ]);
            
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->line("   ✓ POST request completed ({$elapsed}ms) - Status: {$response->status()}");
            
        } catch (\Throwable $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->error("   ✗ POST request failed ({$elapsed}ms): " . $e->getMessage());
        }

        // Test 4: Payment/Start with minimal payload
        $this->info('4. Testing Payment/Start endpoint...');
        
        $payload = [
            'POSKey' => $posKey,
            'PaymentType' => 'Immediate',
            'GuestCheckOut' => true,
            'FundingSources' => ['All'],
            'PaymentRequestId' => 'test_' . time(),
            'Locale' => 'hu-HU',
            'Currency' => 'HUF',
            'RedirectUrl' => 'https://example.com',
            'CallbackUrl' => 'https://example.com',
            'Transactions' => [[
                'POSTransactionId' => 'T-' . time(),
                'Payee' => $payeeEmail,
                'Total' => 1000,
                'Items' => [[
                    'Name' => 'Test',
                    'Description' => 'Test',
                    'Quantity' => 1,
                    'Unit' => 'db',
                    'UnitPrice' => 1000,
                    'ItemTotal' => 1000,
                ]]
            ]]
        ];

        $start = microtime(true);
        
        try {
            $response = Http::timeout(20)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($apiUrl . '/v2/Payment/Start', $payload);
            
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $data = $response->json();
            
            $this->line("   ✓ Payment/Start responded ({$elapsed}ms)");
            $this->line("   HTTP Status: {$response->status()}");
            
            if (isset($data['PaymentId'])) {
                $this->info("   ✓ SUCCESS - PaymentId: " . $data['PaymentId']);
                $this->newLine();
                $this->info('✅ All tests passed!');
                return 0;
            } elseif (isset($data['Errors'])) {
                $this->warn("   ⚠ API validation errors (connection OK):");
                foreach ($data['Errors'] as $error) {
                    $this->line('      - ' . ($error['Title'] ?? 'Unknown error'));
                }
                $this->newLine();
                $this->info('✅ Connection works (validation errors expected)');
                return 0;
            }
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $elapsed = round((microtime(true) - $start) * 1000, 2);
            $this->error("   ✗ Payment/Start timeout ({$elapsed}ms)");
            $this->newLine();
            
            $this->warn('💡 Diagnosis:');
            $this->line('   • Payment/Start endpoint specifically is slow/broken');
            $this->line('   • This appears to be a Barion API issue, not your app');
            
            if (!$useProduction) {
                $this->newLine();
                $this->line('Try testing production API: php artisan test:barion --production');
            }
            
            return 1;
        }
    }
}