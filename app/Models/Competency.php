<?php

namespace App\Models;

use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Model;

class Competency extends Model
{
    protected $table = 'competency';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];
    protected $fillable = ['name', 'name_json', 'organization_id', 'original_language', 'available_languages'];

    protected $casts = [
        'name_json' => 'array',
        'available_languages' => 'array',
    ];

    // ================================
    // RELATIONSHIPS
    // ================================

    public function questions()
    {
        return $this->hasMany(CompetencyQuestion::class, 'competency_id')
            ->whereNull('competency_question.removed_at');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_competency', 'competency_id', 'user_id', 'id', 'id');
    }

    // ================================
    // SCOPES
    // ================================

    public function scopeGlobal($q)
    {
        return $q->whereNull('organization_id');
    }

    public function scopeForOrg($q, $orgId)
    {
        return $q->where('organization_id', $orgId);
    }

    // ================================
    // TRANSLATION METHODS
    // ================================

    /**
     * Get the translated name for the specified locale, with fallback
     */
    public function getTranslatedName(string $locale = null): string
    {
        if ($locale === null) {
            $locale = LanguageService::getCurrentLocale();
        }

        // If we have JSON translations, use them
        if ($this->name_json && is_array($this->name_json)) {
            // Try the requested locale (with empty string check)
            if (isset($this->name_json[$locale]) && !empty(trim($this->name_json[$locale]))) {
                return $this->name_json[$locale];
            }

            // Fallback to original language (with empty string check)
            if ($this->original_language && 
                isset($this->name_json[$this->original_language]) && 
                !empty(trim($this->name_json[$this->original_language]))) {
                return $this->name_json[$this->original_language];
            }

            // Fallback to first available non-empty translation
            $availableTranslations = array_filter($this->name_json, function($value) {
                return !empty(trim($value));
            });
            
            if (!empty($availableTranslations)) {
                return array_values($availableTranslations)[0];
            }
        }

        // Final fallback to old 'name' field
        return $this->name ?? '';
    }

    /**
     * Set translation for a specific language
     */
    public function setTranslation(string $locale, string $name): void
    {
        $translations = $this->name_json ?? [];
        $translations[$locale] = trim($name); // Trim whitespace
        $this->name_json = $translations;

        // Update available languages
        $availableLanguages = $this->available_languages ?? [];
        if (!in_array($locale, $availableLanguages)) {
            $availableLanguages[] = $locale;
            $this->available_languages = $availableLanguages;
        }
    }

    /**
     * Remove translation for a specific language
     */
    public function removeTranslation(string $locale): void
    {
        $translations = $this->name_json ?? [];
        unset($translations[$locale]);
        $this->name_json = $translations;

        // Update available languages
        $availableLanguages = $this->available_languages ?? [];
        $this->available_languages = array_values(array_diff($availableLanguages, [$locale]));
    }

    /**
     * Check if translation exists for a language
     */
    public function hasTranslation(string $locale): bool
    {
        return isset($this->name_json[$locale]) && !empty(trim($this->name_json[$locale]));
    }

    /**
     * Check if the competency has complete translations for a language
     * (For competency, this is the same as hasTranslation since there's only one field)
     */
    public function isTranslationComplete(string $locale): bool
    {
        return $this->hasTranslation($locale);
    }

    /**
     * Check if competency has complete translations for all given languages
     */
    public function hasCompleteTranslations(array $languages): bool
    {
        foreach ($languages as $language) {
            if (!$this->hasTranslation($language)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check if competency has any incomplete translations in given languages
     */
    public function hasMissingTranslations(array $languages): bool
    {
        return !$this->hasCompleteTranslations($languages);
    }

    /**
     * Check if this competency needs translation warning
     */
    public function needsTranslationWarning(array $selectedLanguages): bool
    {
        return $this->hasMissingTranslations($selectedLanguages);
    }

    // ================================
    // TRANSLATION INFO
    // ================================

    /**
     * Get all available languages for this competency
     */
    public function getAvailableLanguages(): array
    {
        return $this->available_languages ?? [];
    }

    /**
     * Get missing languages (available in system but not translated)
     */
    public function getMissingLanguages(): array
    {
        $availableLanguages = LanguageService::getAvailableLanguages();
        $competencyLanguages = $this->available_languages ?? [];
        
        return array_values(array_diff($availableLanguages, $competencyLanguages));
    }

    /**
     * Get count of missing translations for given languages
     */
    public function getMissingTranslationCount(array $languages): int
    {
        $missing = 0;
        foreach ($languages as $language) {
            if (!$this->hasTranslation($language)) {
                $missing++;
            }
        }
        return $missing;
    }

    /**
     * Get languages where translation exists (non-empty)
     */
    public function getTranslatedLanguages(): array
    {
        if (!$this->name_json) {
            return [];
        }
        
        return array_keys(array_filter($this->name_json, function($value) {
            return !empty(trim($value));
        }));
    }

    // ================================
    // AUTO-ACCESSOR (Backward Compatibility)
    // ================================

    /**
     * Auto-accessor for name - returns translated name for current locale
     */
    public function getNameAttribute($value)
    {
        // If we're in a migration context or name_json is not set, return the original value
        if (!$this->name_json) {
            return $value;
        }

        return $this->getTranslatedName();
    }

    // ================================
    // JSON ATTRIBUTE CASTING (FIXED - No Duplicates)
    // ================================

    /**
     * Cast JSON fields properly
     */
    protected function getNameJsonAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?? [];
        }
        
        return $value ?? [];
    }

    protected function setNameJsonAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['name_json'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $this->attributes['name_json'] = $value;
        }
    }

    protected function getAvailableLanguagesAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?? [];
        }
        
        return $value ?? [];
    }

    protected function setAvailableLanguagesAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['available_languages'] = json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $this->attributes['available_languages'] = $value;
        }
    }
}