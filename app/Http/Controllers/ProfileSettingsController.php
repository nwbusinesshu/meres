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
        
        // Find which option is currently active
        $currentIndex = 0;
        foreach ($availableOptions as $index => $option) {
            if ($option['filename'] === $user->profile_pic) {
                $currentIndex = $index;
                break;
            }
        }
        
        return response()->json([
            'success' => true,
            'current_pic' => $currentPic,
            'current_index' => $currentIndex,
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
}