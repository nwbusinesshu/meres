<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfilePicService
{
    /**
     * Available monster profile pictures
     */
    const MONSTER_COLORS = [
        'blue',
        'green',
        'pink',
        'purple',
        'red',
        'rose',
        'turquoise',
        'yellow',
    ];

    /**
     * Assign a random monster profile pic to a user
     * This is called during registration/user creation
     * 
     * @param User $user
     * @return bool Success status
     */
    public static function assignRandomMonster(User $user): bool
    {
        try {
            // Pick a random monster color
            $randomColor = self::MONSTER_COLORS[array_rand(self::MONSTER_COLORS)];
            $sourcePath = public_path("assets/img/monster_profiles/monster-profile-pic-{$randomColor}.svg");
            
            // Check if source exists
            if (!file_exists($sourcePath)) {
                Log::error('profile_pic.monster_not_found', [
                    'user_id' => $user->id,
                    'color' => $randomColor,
                    'path' => $sourcePath
                ]);
                return false;
            }
            
            // Ensure target directory exists
            $targetDir = public_path('uploads/profile_pics');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            // Copy to user's profile pic
            $targetPath = $targetDir . "/{$user->id}.svg";
            if (!copy($sourcePath, $targetPath)) {
                Log::error('profile_pic.copy_failed', [
                    'user_id' => $user->id,
                    'source' => $sourcePath,
                    'target' => $targetPath
                ]);
                return false;
            }
            
            // Update user record
            $user->profile_pic = "{$user->id}.svg";
            $user->save();
            
            Log::info('profile_pic.monster_assigned', [
                'user_id' => $user->id,
                'color' => $randomColor,
                'filename' => $user->profile_pic
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('profile_pic.assignment_error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Download and save profile pic from OAuth provider
     * This is called during OAuth login (Google/Microsoft)
     * 
     * @param User $user
     * @param string|null $avatarUrl URL from OAuth provider
     * @return bool Success status (false means keep existing pic)
     */
    public static function downloadOAuthPicture(User $user, ?string $avatarUrl): bool
    {
        // If no avatar URL provided, don't change anything
        if (empty($avatarUrl)) {
            Log::info('profile_pic.oauth_no_url', [
                'user_id' => $user->id
            ]);
            return false;
        }
        
        try {
            // Download the image
            $imageContent = @file_get_contents($avatarUrl);
            
            if ($imageContent === false) {
                Log::warning('profile_pic.oauth_download_failed', [
                    'user_id' => $user->id,
                    'url' => $avatarUrl
                ]);
                return false;
            }
            
            // Detect image type from content
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);
            
            // Determine extension
            $extension = 'jpg'; // default
            if (str_contains($mimeType, 'png')) {
                $extension = 'png';
            } elseif (str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg')) {
                $extension = 'jpg';
            } elseif (str_contains($mimeType, 'gif')) {
                $extension = 'gif';
            } elseif (str_contains($mimeType, 'webp')) {
                $extension = 'webp';
            }
            
            // Ensure target directory exists
            $targetDir = public_path('uploads/profile_pics');
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            // Delete old profile pic if it exists
            if ($user->profile_pic) {
                $oldPath = $targetDir . '/' . $user->profile_pic;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            
            // Save new image
            $filename = "{$user->id}.{$extension}";
            $targetPath = $targetDir . '/' . $filename;
            
            if (file_put_contents($targetPath, $imageContent) === false) {
                Log::error('profile_pic.oauth_save_failed', [
                    'user_id' => $user->id,
                    'path' => $targetPath
                ]);
                return false;
            }
            
            // Update user record
            $user->profile_pic = $filename;
            $user->save();
            
            Log::info('profile_pic.oauth_downloaded', [
                'user_id' => $user->id,
                'filename' => $filename,
                'mime_type' => $mimeType
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('profile_pic.oauth_error', [
                'user_id' => $user->id,
                'url' => $avatarUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the full URL of a user's profile picture
     * 
     * @param User|null $user
     * @return string|null Full URL or null if no profile pic
     */
    public static function getProfilePicUrl(?User $user): ?string
    {
        if (!$user || !$user->profile_pic) {
            return null;
        }
        
        return asset('uploads/profile_pics/' . $user->profile_pic);
    }

    /**
     * Check if user has a profile picture
     * 
     * @param User $user
     * @return bool
     */
    public static function hasProfilePic(User $user): bool
    {
        return !empty($user->profile_pic) && 
               file_exists(public_path('uploads/profile_pics/' . $user->profile_pic));
    }
}