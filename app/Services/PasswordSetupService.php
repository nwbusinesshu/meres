<?php

namespace App\Services;

use App\Mail\PasswordResetMail;
use App\Mail\PasswordSetupMail;
use App\Models\Organization;
use App\Models\PasswordSetup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordSetupService
{
    // MEGLÉVŐ createAndSend(...) maradhat, de küldjön setup levelet:
    public static function createAndSend(int $orgId, int $userId, ?int $createdBy = null): string
    {
        return self::createAndSendInternal($orgId, $userId, $createdBy, purpose: 'setup');
    }

    // ÚJ: reset útvonal
    public static function createAndSendReset(int $orgId, int $userId, ?int $createdBy = null): string
    {
        return self::createAndSendInternal($orgId, $userId, $createdBy, purpose: 'reset');
    }

    // KÖZÖS belső
    protected static function createAndSendInternal(int $orgId, int $userId, ?int $createdBy, string $purpose): string
    {
        /** @var Organization $org */
        $org  = Organization::findOrFail($orgId);
        /** @var User $user */
        $user = User::findOrFail($userId);

        $plainToken = Str::random(64);
        $tokenHash  = hash('sha256', $plainToken);
        $expiresAt  = CarbonImmutable::now()->addWeek();

        DB::table('password_setup')->insert([
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'token_hash'      => $tokenHash,
            'created_by'      => $createdBy,
            'created_at'      => now(),
            'expires_at'      => $expiresAt,
            'used_at'         => null,
        ]);

        // FIXED: URL generation without org slug - matches the GET route
        $url = route('password-setup.show', ['token' => $plainToken]);

        // Determine locale based on purpose
        if ($purpose === 'reset') {
            // Password reset: Use target user's locale (or fallback to English)
            $locale = $user->locale ?? config('app.fallback_locale', 'en');
            Mail::to($user->email)->send(new PasswordResetMail($org, $user, $url, $expiresAt, $locale));
        } else {
            // Password setup: Use admin's locale (who created the user)
            // If no admin logged in (e.g., registration), use current session locale
            $adminLocale = null;
            if ($createdBy) {
                $admin = User::find($createdBy);
                $adminLocale = $admin?->locale;
            }
            $locale = $adminLocale ?? app()->getLocale() ?? config('app.fallback_locale', 'en');
            
            Mail::to($user->email)->send(new PasswordSetupMail($org, $user, $url, $expiresAt, $locale));
        }

        \Log::info('password-setup.mail.sent', [
            'purpose' => $purpose,
            'org_id'  => $org->id,
            'user_id' => $user->id,
            'to'      => $user->email,
            'url'     => $url,
            'locale'  => $locale ?? 'unknown',
        ]);

        return $plainToken;
    }
}