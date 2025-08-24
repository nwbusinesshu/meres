<?php

namespace App\Services;

use App\Mail\PasswordResetMail;
use App\Mail\PasswordSetupMail;
use App\Models\Organization;
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

        // URL összeállítás
        $url = url(sprintf('/%s/password-setup/%s', $org->slug, $plainToken));

        // A megfelelőt küldjük
        if ($purpose === 'reset') {
            Mail::to($user->email)->send(new PasswordResetMail($org, $user, $url, $expiresAt));
        } else {
            Mail::to($user->email)->send(new PasswordSetupMail($org, $user, $url, $expiresAt));
        }

        \Log::info('password-setup.mail.sent', [
            'purpose' => $purpose,
            'org_id'  => $org->id,
            'user_id' => $user->id,
            'to'      => $user->email,
        ]);

        return $plainToken;
    }
}
