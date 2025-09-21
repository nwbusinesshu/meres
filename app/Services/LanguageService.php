<?php

namespace App\Services;

class LanguageService
{
    /**
     * Get available languages from SetLocale middleware
     * This should match the $allowed array in SetLocale middleware
     */
    public static function getAvailableLanguages(): array
    {
        // For now, read from SetLocale middleware
        // Later this could be made configurable via database
        return ['hu', 'en'];
    }

    /**
     * Get the current application locale
     */
    public static function getCurrentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Get the fallback locale (usually the original language)
     */
    public static function getFallbackLocale(): string
    {
        return config('app.locale', 'hu');
    }

    /**
     * Check if a language code is valid/supported
     */
    public static function isValidLanguage(string $locale): bool
    {
        return in_array($locale, self::getAvailableLanguages(), true);
    }

    /**
     * Get language display names
     */
    public static function getLanguageNames(): array
    {
        return [
            'hu' => 'Magyar',
            'en' => 'English',
            // Add more as needed
        ];
    }

    /**
     * Get display name for a language code
     */
    public static function getLanguageName(string $locale): string
    {
        $names = self::getLanguageNames();
        return $names[$locale] ?? strtoupper($locale);
    }
}