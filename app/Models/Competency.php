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

    public function questions()
    {
        return $this->hasMany(CompetencyQuestion::class, 'competency_id')
            ->whereNull('competency_question.removed_at');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_competency', 'competency_id', 'user_id', 'id', 'id');
    }

    public function scopeGlobal($q)
    {
        return $q->whereNull('organization_id');
    }

    public function scopeForOrg($q, $orgId)
    {
        return $q->where('organization_id', $orgId);
    }

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
            // Try the requested locale
            if (isset($this->name_json[$locale])) {
                return $this->name_json[$locale];
            }

            // Fallback to original language
            if (isset($this->name_json[$this->original_language])) {
                return $this->name_json[$this->original_language];
            }

            // Fallback to first available translation
            if (!empty($this->name_json)) {
                return array_values($this->name_json)[0];
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
        $translations[$locale] = $name;
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
        return isset($this->name_json[$locale]) && !empty($this->name_json[$locale]);
    }

    /**
     * Get all available languages for this competency
     */
    public function getAvailableLanguages(): array
    {
        return $this->available_languages ?? [];
    }

    /**
     * Check if the competency has complete translations for a language
     */
    public function isTranslationComplete(string $locale): bool
    {
        return $this->hasTranslation($locale);
    }

    /**
     * Get missing languages (available in system but not translated)
     */
    public function getMissingLanguages(): array
    {
        $systemLanguages = LanguageService::getAvailableLanguages();
        $availableLanguages = $this->getAvailableLanguages();
        return array_diff($systemLanguages, $availableLanguages);
    }

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
}