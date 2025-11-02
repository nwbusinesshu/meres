<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentHelper
{
    /**
     * Calculate payment amounts with VAT based on organization's country
     * 
     * @param int $organizationId
     * @param int $employeeCount
     * @return array ['currency' => 'HUF'|'EUR', 'net_amount' => float, 'vat_rate' => float, 'vat_amount' => float, 'gross_amount' => float]
     */
    public static function calculatePaymentAmounts(int $organizationId, int $employeeCount): array
    {
        // Get organization profile
        $profile = DB::table('organization_profiles')
            ->where('organization_id', $organizationId)
            ->first();
        
        if (!$profile) {
            Log::error('payment.calculate.no_profile', ['org_id' => $organizationId]);
            throw new \Exception('Organization profile not found');
        }
        
        // Get country code
        $countryCode = strtoupper($profile->country_code ?? 'HU');
        $isHungary = ($countryCode === 'HU');
        
        // Determine currency based on country
        $currency = $isHungary ? 'HUF' : 'EUR';
        
        // Get price per user from config
        $configName = $isHungary ? 'global_price_huf' : 'global_price_eur';
        $userPrice = DB::table('config')
            ->where('name', $configName)
            ->value('value');
        
        if (!$userPrice) {
            // Fallback to default prices
            $userPrice = $isHungary ? '950' : '2.5';
            Log::warning('payment.calculate.default_price', [
                'org_id' => $organizationId,
                'currency' => $currency,
                'price' => $userPrice
            ]);
        }
        
        // Calculate net amount
        $netAmount = round((float)$userPrice * $employeeCount, 2);
        
        // Calculate VAT (27% for Hungary, 0% for other countries - reverse charge)
        $vatRate = $isHungary ? 0.27 : 0.00;
        $vatAmount = round($netAmount * $vatRate, 2);
        
        // Calculate gross amount (total to pay)
        $grossAmount = round($netAmount + $vatAmount, 2);
        
        Log::info('payment.calculate.amounts', [
            'org_id' => $organizationId,
            'country' => $countryCode,
            'currency' => $currency,
            'employee_count' => $employeeCount,
            'user_price' => $userPrice,
            'net_amount' => $netAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'gross_amount' => $grossAmount,
        ]);
        
        return [
            'currency' => $currency,
            'net_amount' => $netAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'gross_amount' => $grossAmount,
        ];
    }
    
    /**
     * Format amount for display based on currency
     * 
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function formatAmount(float $amount, string $currency): string
    {
        if ($currency === 'HUF') {
            return number_format($amount, 0, ',', ' ') . ' HUF';
        } else {
            return number_format($amount, 2, '.', ',') . ' EUR';
        }
    }
}