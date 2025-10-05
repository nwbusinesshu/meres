<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    private const VERIFY_ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    public static function isEnabled(): bool
    {
        return filled(config('services.recaptcha.key')) && filled(config('services.recaptcha.secret'));
    }

    public static function validateRequest(Request $request): bool
    {
        if (! self::isEnabled()) {
            return true;
        }

        $token = $request->input('g-recaptcha-response');

        return self::verifyToken(is_string($token) ? trim($token) : '', $request->ip());
    }

    public static function verifyToken(?string $token, ?string $ip = null): bool
    {
        if (! self::isEnabled()) {
            return true;
        }

        $token = trim((string) $token);
        if ($token === '') {
            return false;
        }

        try {
            $payload = [
                'secret'   => config('services.recaptcha.secret'),
                'response' => $token,
            ];

            if ($ip) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::asForm()
                ->timeout(5)
                ->post(self::VERIFY_ENDPOINT, $payload);

            if (! $response->ok()) {
                Log::warning('reCAPTCHA verification HTTP error', [
                    'status' => $response->status(),
                ]);

                return false;
            }

            $payload = $response->json();
            $success = (bool) ($payload['success'] ?? false);

            if (! $success) {
                Log::info('reCAPTCHA verification failed', [
                    'error_codes' => $payload['error-codes'] ?? null,
                ]);
            }

            return $success;
        } catch (\Throwable $e) {
            Log::error('reCAPTCHA verification exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}