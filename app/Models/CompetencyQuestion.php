<?php

namespace App\Models;

use App\Services\LanguageService;
use Illuminate\Database\Eloquent\Model;

class CompetencyQuestion extends Model
{
    protected $table = 'competency_question';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];
    protected $fillable = [
        'organization_id', 'competency_id', 'question', 'question_self',
        'question_json', 'question_self_json', 'min_label', 'max_label',
        'min_label_json', 'max_label_json', 'max_value', 'original_language', 'available_languages'
    ];

    protected $casts = [
        'question_json' => 'array',
        'question_self_json' => 'array',
        'min_label_json' => 'array',
        'max_label_json' => 'array',
        'available_languages' => 'array',
    ];

    // ================================
    // RELATIONSHIPS
    // ================================

    public function competency()
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'id');
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
    // TRANSLATION GETTERS
    // ================================

    /**
     * Get translated question for rating others
     */
    public function getTranslatedQuestion(string $locale = null): string
    {
        if ($locale === null) {
            $locale = LanguageService::getCurrentLocale();
        }

        return $this->getTranslatedField('question_json', 'question', $locale);
    }

    /**
     * Get translated question for self-rating
     */
    public function getTranslatedQuestionSelf(string $locale = null): string
    {
        if ($locale === null) {
            $locale = LanguageService::getCurrentLocale();
        }

        return $this->getTranslatedField('question_self_json', 'question_self', $locale);
    }

    /**
     * Get translated minimum label
     */
    public function getTranslatedMinLabel(string $locale = null): string
    {
        if ($locale === null) {
            $locale = LanguageService::getCurrentLocale();
        }

        return $this->getTranslatedField('min_label_json', 'min_label', $locale);
    }

    /**
     * Get translated maximum label
     */
    public function getTranslatedMaxLabel(string $locale = null): string
    {
        if ($locale === null) {
            $locale = LanguageService::getCurrentLocale();
        }

        return $this->getTranslatedField('max_label_json', 'max_label', $locale);
    }

    /**
     * Generic method to get translated field with fallback
     */
    private function getTranslatedField(string $jsonField, string $fallbackField, string $locale): string
    {
        $jsonData = $this->{$jsonField};

        if ($jsonData && is_array($jsonData)) {
            // Try the requested locale
            if (isset($jsonData[$locale]) && !empty(trim($jsonData[$locale]))) {
                return $jsonData[$locale];
            }

            // Fallback to original language
            if ($this->original_language && isset($jsonData[$this->original_language]) && !empty(trim($jsonData[$this->original_language]))) {
                return $jsonData[$this->original_language];
            }

            // Fallback to first available non-empty translation
            $availableTranslations = array_filter($jsonData, function($value) {
                return !empty(trim($value));
            });
            
            if (!empty($availableTranslations)) {
                return array_values($availableTranslations)[0];
            }
        }

        // Final fallback to legacy field
        return $this->{$fallbackField} ?? '';
    }

    // ================================
    // TRANSLATION SETTERS/MANAGEMENT
    // ================================

    /**
     * Set translation for all question fields in a specific language
     */
    public function setTranslation(string $locale, array $data): void
    {
        // Update question
        if (isset($data['question'])) {
            $questionTranslations = $this->question_json ?? [];
            $questionTranslations[$locale] = $data['question'];
            $this->question_json = $questionTranslations;
        }

        // Update question_self
        if (isset($data['question_self'])) {
            $questionSelfTranslations = $this->question_self_json ?? [];
            $questionSelfTranslations[$locale] = $data['question_self'];
            $this->question_self_json = $questionSelfTranslations;
        }

        // Update min_label
        if (isset($data['min_label'])) {
            $minLabelTranslations = $this->min_label_json ?? [];
            $minLabelTranslations[$locale] = $data['min_label'];
            $this->min_label_json = $minLabelTranslations;
        }

        // Update max_label
        if (isset($data['max_label'])) {
            $maxLabelTranslations = $this->max_label_json ?? [];
            $maxLabelTranslations[$locale] = $data['max_label'];
            $this->max_label_json = $maxLabelTranslations;
        }

        // Update max_value (scale) - this affects all languages
        if (isset($data['max_value'])) {
            $this->max_value = $data['max_value'];
        }

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
        $jsonFields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];

        foreach ($jsonFields as $field) {
            $translations = $this->{$field} ?? [];
            unset($translations[$locale]);
            $this->{$field} = $translations;
        }

        // Update available languages
        $availableLanguages = $this->available_languages ?? [];
        $this->available_languages = array_values(array_diff($availableLanguages, [$locale]));
    }

    // ================================
    // TRANSLATION CHECKS
    // ================================

    /**
     * Check if translation exists for a language (all fields must be present)
     */
    public function hasTranslation(string $locale): bool
    {
        return $this->isTranslationComplete($locale);
    }

    /**
     * Check if the question has complete translations for a language
     * (all four fields must be present and non-empty)
     */
    public function isTranslationComplete(string $locale): bool
    {
        $requiredFields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];

        foreach ($requiredFields as $field) {
            $data = $this->{$field} ?? [];
            if (!isset($data[$locale]) || empty(trim($data[$locale]))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the question has partial translation for a language
     * (some but not all fields are translated)
     */
    public function hasPartialTranslation(string $locale): bool
    {
        if ($this->isTranslationComplete($locale)) {
            return false; // Not partial if complete
        }

        $fields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];
        $translatedCount = 0;

        foreach ($fields as $field) {
            $data = $this->{$field} ?? [];
            if (isset($data[$locale]) && !empty(trim($data[$locale]))) {
                $translatedCount++;
            }
        }

        return $translatedCount > 0;
    }

    /**
     * Check if question has complete translation for a language (alias for isTranslationComplete)
     */
    public function hasCompleteTranslation(string $locale): bool
    {
        return $this->isTranslationComplete($locale);
    }

    /**
     * Check if question has complete translations for all given languages
     */
    public function hasCompleteTranslations(array $languages): bool
    {
        foreach ($languages as $language) {
            if (!$this->hasCompleteTranslation($language)) {
                return false;
            }
        }
        return true;
    }

    // ================================
    // TRANSLATION INFO
    // ================================

    /**
     * Get missing field names for a specific language
     */
    public function getMissingFields(string $locale): array
    {
        $fields = [
            'question_json' => 'question',
            'question_self_json' => 'question_self',
            'min_label_json' => 'min_label',
            'max_label_json' => 'max_label'
        ];

        $missing = [];
        foreach ($fields as $jsonField => $displayName) {
            $data = $this->{$jsonField} ?? [];
            if (!isset($data[$locale]) || empty(trim($data[$locale]))) {
                $missing[] = $displayName;
            }
        }

        return $missing;
    }

    /**
     * Get all available languages for this question
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
        $systemLanguages = LanguageService::getAvailableLanguages();
        $availableLanguages = $this->getAvailableLanguages();
        return array_diff($systemLanguages, $availableLanguages);
    }

    // ================================
    // AUTO-ACCESSORS (Backward Compatibility)
    // ================================

    /**
     * Auto-accessor for question - returns translated question for current locale
     */
    public function getQuestionAttribute($value)
    {
        // If we're in a migration context or question_json is not set, return the original value
        if (!$this->question_json) {
            return $value;
        }

        return $this->getTranslatedQuestion();
    }

    /**
     * Auto-accessor for question_self - returns translated question_self for current locale
     */
    public function getQuestionSelfAttribute($value)
    {
        if (!$this->question_self_json) {
            return $value;
        }

        return $this->getTranslatedQuestionSelf();
    }

    /**
     * Auto-accessor for min_label - returns translated min_label for current locale
     */
    public function getMinLabelAttribute($value)
    {
        if (!$this->min_label_json) {
            return $value;
        }

        return $this->getTranslatedMinLabel();
    }

    /**
     * Auto-accessor for max_label - returns translated max_label for current locale
     */
    public function getMaxLabelAttribute($value)
    {
        if (!$this->max_label_json) {
            return $value;
        }

        return $this->getTranslatedMaxLabel();
    }
}