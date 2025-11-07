<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetMail;
use App\Mail\PasswordSetupMail;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class EmailPreviewController extends Controller
{
    public function preview(Request $request)
    {
        $type = $request->get('type', 'reset'); // 'reset' or 'setup'
        
        // Get a test organization and user (first ones from DB)
        $org = Organization::first();
        $user = User::first();
        
        if (!$org || !$user) {
            return 'No organization or user found in database. Please create test data first.';
        }
        
        $url = config('app.url') . '/password-setup/test-token-12345';
        $expiresAt = CarbonImmutable::now()->addWeek();
        
        // Get locale from query parameter, default to 'hu'
        $locale = $request->get('locale', 'hu');
        
        if ($type === 'setup') {
            // Password setup email (markdown with quarma360 theme)
            return new PasswordSetupMail($org, $user, $url, $expiresAt, $locale);
        } else {
            // Password reset email (markdown with quarma360 theme)
            return new PasswordResetMail($org, $user, $url, $expiresAt, $locale);
        }
    }
}