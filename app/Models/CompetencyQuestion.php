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

    public function competency()
    {
        return $this->belongsTo(Competency::class, 'competency_id', 'id');
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
            if (isset($jsonData[$locale])) {
                return $jsonData[$locale];
            }

            // Fallback to original language
            if (isset($jsonData[$this->original_language])) {
                return $jsonData[$this->original_language];
            }

            // Fallback to first available translation
            if (!empty($jsonData)) {
                return array_values($jsonData)[0];
            }
        }

        // Final fallback to old field
        return $this->{$fallbackField} ?? '';
    }

    /**
     * Set translation for all question fields in a specific language
     */
    public function setTranslation(string $locale, array $translations): void
    {
        $fields = ['question', 'question_self', 'min_label', 'max_label'];

        foreach ($fields as $field) {
            if (isset($translations[$field])) {
                $jsonField = $field . '_json';
                $currentTranslations = $this->{$jsonField} ?? [];
                $currentTranslations[$locale] = $translations[$field];
                $this->{$jsonField} = $currentTranslations;
            }
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
        $fields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];

        foreach ($fields as $field) {
            $translations = $this->{$field} ?? [];
            unset($translations[$locale]);
            $this->{$field} = $translations;
        }

        // Update available languages
        $availableLanguages = $this->available_languages ?? [];
        $this->available_languages = array_values(array_diff($availableLanguages, [$locale]));
    }

    /**
     * Check if translation exists for a language
     */
    public function hasTranslation(string $locale): bool
    {
        return $this->isTranslationComplete($locale);
    }

    /**
     * Check if the question has complete translations for a language
     * (all four fields must be present)
     */
    public function isTranslationComplete(string $locale): bool
    {
        $requiredFields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];

        foreach ($requiredFields as $field) {
            $data = $this->{$field} ?? [];
            if (!isset($data[$locale]) || empty($data[$locale])) {
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
        $fields = ['question_json', 'question_self_json', 'min_label_json', 'max_label_json'];
        $translatedCount = 0;

        foreach ($fields as $field) {
            $data = $this->{$field} ?? [];
            if (isset($data[$locale]) && !empty($data[$locale])) {
                $translatedCount++;
            }
        }

        return $translatedCount > 0 && $translatedCount < 4;
    }

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
            if (!isset($data[$locale]) || empty($data[$locale])) {
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

    /**
     * Auto-accessors for backward compatibility
     */
    public function getQuestionAttribute($value)
    {
        if (!$this->question_json) {
            return $value;
        }
        return $this->getTranslatedQuestion();
    }

    public function getQuestionSelfAttribute($value)
    {
        if (!$this->question_self_json) {
            return $value;
        }
        return $this->getTranslatedQuestionSelf();
    }

    public function getMinLabelAttribute($value)
    {
        if (!$this->min_label_json) {
            return $value;
        }
        return $this->getTranslatedMinLabel();
    }

    public function getMaxLabelAttribute($value)
    {
        if (!$this->max_label_json) {
            return $value;
        }
        return $this->getTranslatedMaxLabel();
    }
}