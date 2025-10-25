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
        'turqoise',
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
     * Download and save OAuth profile pic SEPARATELY from user's chosen pic
     * 
     * NEW LOGIC:
     * - Downloads OAuth avatar to separate file: {user_id}_oauth.{extension}
     * - Stores in oauth_profile_pic column (not profile_pic)
     * - Only updates profile_pic if user has no pic yet (first login)
     * 
     * @param User $user
     * @param string|null $avatarUrl URL from OAuth provider
     * @return bool Success status
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
            // Download the image using cURL
            $ch = curl_init($avatarUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Quarma360/1.0');
            
            $imageContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($imageContent === false || $httpCode !== 200) {
                Log::warning('profile_pic.oauth_download_failed', [
                    'user_id' => $user->id,
                    'url' => $avatarUrl,
                    'http_code' => $httpCode,
                    'curl_error' => $curlError
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
            
            // Delete old OAuth pic if it exists
            if ($user->oauth_profile_pic) {
                $oldOAuthPath = $targetDir . '/' . $user->oauth_profile_pic;
                if (file_exists($oldOAuthPath)) {
                    @unlink($oldOAuthPath);
                }
            }
            
            // Save OAuth image with separate filename
            $oauthFilename = "{$user->id}_oauth.{$extension}";
            $oauthTargetPath = $targetDir . '/' . $oauthFilename;
            
            if (file_put_contents($oauthTargetPath, $imageContent) === false) {
                Log::error('profile_pic.oauth_save_failed', [
                    'user_id' => $user->id,
                    'path' => $oauthTargetPath
                ]);
                return false;
            }
            
            // Update oauth_profile_pic column
            $user->oauth_profile_pic = $oauthFilename;
            
            // Only update active profile_pic if user has none (first OAuth login)
            if (empty($user->profile_pic)) {
                $user->profile_pic = $oauthFilename;
            }
            
            $user->save();
            
            Log::info('profile_pic.oauth_downloaded', [
                'user_id' => $user->id,
                'oauth_filename' => $oauthFilename,
                'active_filename' => $user->profile_pic,
                'mime_type' => $mimeType,
                'is_first_login' => empty($user->profile_pic)
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
     * Get the full URL of a user's OAuth profile picture
     * 
     * @param User|null $user
     * @return string|null Full URL or null if no OAuth pic
     */
    public static function getOAuthProfilePicUrl(?User $user): ?string
    {
        if (!$user || !$user->oauth_profile_pic) {
            return null;
        }
        
        return asset('uploads/profile_pics/' . $user->oauth_profile_pic);
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

    /**
     * Check if user has an OAuth profile picture
     * 
     * @param User $user
     * @return bool
     */
    public static function hasOAuthProfilePic(User $user): bool
    {
        return !empty($user->oauth_profile_pic) && 
               file_exists(public_path('uploads/profile_pics/' . $user->oauth_profile_pic));
    }

    /**
     * Get all available profile pic options for a user
     * Returns array with monster colors and oauth pic if exists
     * 
     * @param User $user
     * @return array
     */
    public static function getAvailableProfilePics(User $user): array
    {
        $options = [];
        
        // Add all monster colors
        foreach (self::MONSTER_COLORS as $color) {
            $options[] = [
                'type' => 'monster',
                'color' => $color,
                'filename' => "monster-profile-pic-{$color}.svg",
                'url' => asset("assets/img/monster_profiles/monster-profile-pic-{$color}.svg"),
            ];
        }
        
        // Add OAuth pic if exists
        if (self::hasOAuthProfilePic($user)) {
            $options[] = [
                'type' => 'oauth',
                'color' => null,
                'filename' => $user->oauth_profile_pic,
                'url' => self::getOAuthProfilePicUrl($user),
            ];
        }
        
        return $options;
    }

    /**
     * Update user's active profile pic
     * Can choose from monster colors or OAuth pic
     * 
     * @param User $user
     * @param string $type 'monster' or 'oauth'
     * @param string|null $color Monster color (if type is 'monster')
     * @return bool Success status
     */
    public static function updateProfilePic(User $user, string $type, ?string $color = null): bool
    {
        try {
            $targetDir = public_path('uploads/profile_pics');
            
            if ($type === 'monster') {
                // Validate color
                if (!in_array($color, self::MONSTER_COLORS)) {
                    Log::error('profile_pic.invalid_color', [
                        'user_id' => $user->id,
                        'color' => $color
                    ]);
                    return false;
                }
                
                // Copy monster pic to user's active profile pic
                $sourcePath = public_path("assets/img/monster_profiles/monster-profile-pic-{$color}.svg");
                $targetPath = $targetDir . "/{$user->id}.svg";
                
                if (!file_exists($sourcePath)) {
                    Log::error('profile_pic.monster_not_found', [
                        'user_id' => $user->id,
                        'color' => $color
                    ]);
                    return false;
                }
                
                // Delete old active pic if it exists and it's not the OAuth pic
                if ($user->profile_pic && $user->profile_pic !== $user->oauth_profile_pic) {
                    $oldPath = $targetDir . '/' . $user->profile_pic;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                
                if (!copy($sourcePath, $targetPath)) {
                    Log::error('profile_pic.copy_failed', [
                        'user_id' => $user->id,
                        'source' => $sourcePath,
                        'target' => $targetPath
                    ]);
                    return false;
                }
                
                $user->profile_pic = "{$user->id}.svg";
                
            } elseif ($type === 'oauth') {
                // Switch to OAuth pic
                if (!self::hasOAuthProfilePic($user)) {
                    Log::error('profile_pic.no_oauth_pic', [
                        'user_id' => $user->id
                    ]);
                    return false;
                }
                
                // Delete old active pic if it exists and it's not the OAuth pic
                if ($user->profile_pic && $user->profile_pic !== $user->oauth_profile_pic) {
                    $oldPath = $targetDir . '/' . $user->profile_pic;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                
                // Point to OAuth pic
                $user->profile_pic = $user->oauth_profile_pic;
                
            } else {
                Log::error('profile_pic.invalid_type', [
                    'user_id' => $user->id,
                    'type' => $type
                ]);
                return false;
            }
            
            $user->save();
            
            Log::info('profile_pic.updated', [
                'user_id' => $user->id,
                'type' => $type,
                'color' => $color,
                'filename' => $user->profile_pic
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('profile_pic.update_error', [
                'user_id' => $user->id,
                'type' => $type,
                'color' => $color,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}