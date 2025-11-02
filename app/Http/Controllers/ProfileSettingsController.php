<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ProfilePicService;

class ProfileSettingsController extends Controller
{
    /**
     * Get user's profile settings data for modal
     * Returns current profile pic and available options
     */
    public function getProfileData(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Get current profile pic
        $currentPic = ProfilePicService::getProfilePicUrl($user);
        
        // Get all available options (monsters + OAuth if exists)
        $availableOptions = ProfilePicService::getAvailableProfilePics($user);
        
        // FIXED: Add the user's current profile pic as the first option
        // This ensures it's always selected on modal open, even if it's a monster that creates a duplicate
        $currentIndex = 0;
        
        if ($user->profile_pic) {
            // Determine the type of the current profile pic
            $isOAuthPic = ($user->profile_pic === $user->oauth_profile_pic);
            
            // Prepend current pic to options
            array_unshift($availableOptions, [
                'type' => $isOAuthPic ? 'oauth' : 'monster',
                'color' => null, // We don't need to track color for the current pic
                'filename' => $user->profile_pic,
                'url' => $currentPic,
                'is_current' => true // Mark as current for potential UI indication
            ]);
        }
        
        return response()->json([
            'success' => true,
            'current_pic' => $currentPic,
            'current_index' => $currentIndex, // Always 0 since current pic is first
            'available_options' => $availableOptions,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }
    
    /**
     * Update user's profile picture
     * POST /profile-settings/update-picture
     */
    public function updateProfilePic(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = $request->validate([
            'type' => 'required|in:monster,oauth',
            'color' => 'nullable|string',
        ]);
        
        $type = $data['type'];
        $color = $data['color'] ?? null;
        
        // Validate color if type is monster
        if ($type === 'monster' && empty($color)) {
            return response()->json([
                'success' => false,
                'error' => 'Color is required for monster type'
            ], 400);
        }
        
        // Update profile pic
        $success = ProfilePicService::updateProfilePic($user, $type, $color);
        
        if (!$success) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update profile picture'
            ], 500);
        }
        
        // Refresh user
        $user->refresh();
        
        // Update session avatar
        $newAvatarUrl = ProfilePicService::getProfilePicUrl($user);
        session(['uavatar' => $newAvatarUrl]);
        
        return response()->json([
            'success' => true,
            'new_avatar_url' => $newAvatarUrl,
            'message' => 'Profilkép sikeresen frissítve!'
        ]);
    }

    /**
     * Save employee's privacy policy acknowledgment
     * POST /profile-settings/acknowledge-privacy
     */
    public function acknowledgePrivacy(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Check if already acknowledged
        if ($user->privacy_policy_accepted_at) {
            return response()->json([
                'success' => true,
                'already_acknowledged' => true,
                'message' => 'Privacy policy already acknowledged'
            ]);
        }
        
        // Save acknowledgment with timestamp and IP
        $user->privacy_policy_accepted_at = now();
        $user->privacy_policy_accepted_ip = $request->ip();
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Privacy policy acknowledged successfully'
        ]);
    }
}