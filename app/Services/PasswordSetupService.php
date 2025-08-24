<?php

namespace App\Services;

use App\Mail\PasswordSetupMail;
use App\Models\Organization;
use App\Models\PasswordSetup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordSetupService
{
    public static function createAndSend(int $organizationId, int $userId, ?int $creatorUserId = null, int $ttlDays = 7): PasswordSetup
    {
        $org  = Organization::findOrFail($organizationId);
        $user = User::findOrFail($userId);

        $token = Str::random(64);

        $ps = PasswordSetup::create([
            'organization_id' => $org->id,
            'user_id'         => $user->id,
            'token_hash'      => hash('sha256', $token),
            'created_by'      => $creatorUserId,
            'expires_at'      => now()->addDays($ttlDays),
        ]);

        Mail::to($user->email)->send(new PasswordSetupMail($ps, $org->slug, $token));

        return $ps;
    }
}
