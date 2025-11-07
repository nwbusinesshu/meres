<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiTranslationService
{
    private string $apiKey;
    private string $model;
    private int $timeout;

      public function __construct()
    {
        $this->apiKey = config('services.openai.key');
        $this->model = config('services.openai.model', 'gpt-4o-mini');
        $this->timeout = (int) config('services.openai.timeout', 30);
    }

    /**
     * Translate competency name and description to multiple languages
     * 
     * @param string $competencyName The competency name to translate
     * @param string|null $competencyDescription The competency description to translate (optional)
     * @param string $sourceLanguage Source language code (e.g., 'hu', 'en')
     * @param array $targetLanguages Array of target language codes
     * @return array|null Array of translations or null on failure
     */
    public function translateCompetencyName(string $competencyName, ?string $competencyDescription, string $sourceLanguage, array $targetLanguages): ?array
    {
        if (!$this->apiKey) {
            Log::warning('AI translation aborted: missing OPENAI_API_KEY');
            return null;
        }

        // Remove source language from targets to avoid duplicates
        $targetLanguages = array_diff($targetLanguages, [$sourceLanguage]);
        
        if (empty($targetLanguages)) {
            return [];
        }

        $languageNames = $this->getLanguageNames();
        $prompt = $this->buildCompetencyNamePrompt($competencyName, $competencyDescription, $sourceLanguage, $targetLanguages, $languageNames);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator specializing in business and HR terminology. You maintain the meaning and professional tone while adapting to each language\'s business context.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 1,
            ]);

            if (!$response->ok()) {
                Log::error('AI translation API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Log::error('AI translation: empty response content');
                return null;
            }

            $translations = json_decode($content, true);
            
            if (!is_array($translations) || !isset($translations['translations'])) {
                Log::error('AI translation: invalid response format', ['content' => $content]);
                return null;
            }

            Log::info('AI competency translation successful', [
                'source' => $sourceLanguage,
                'targets' => $targetLanguages,
                'original_name' => $competencyName,
                'has_description' => !empty($competencyDescription)
            ]);

            return $translations['translations'];

        } catch (\Throwable $e) {
            Log::error('AI translation exception', [
                'message' => $e->getMessage(),
                'competency' => $competencyName,
                'source' => $sourceLanguage,
                'targets' => $targetLanguages
            ]);
            return null;
        }
    }

    /**
     * Translate competency question with all its components
     * 
     * @param array $questionData Array containing question, question_self, min_label, max_label
     * @param string $sourceLanguage Source language code
     * @param array $targetLanguages Array of target language codes
     * @return array|null Array of translations for each field or null on failure
     */
    public function translateCompetencyQuestion(array $questionData, string $sourceLanguage, array $targetLanguages): ?array
    {
        if (!$this->apiKey) {
            Log::warning('AI translation aborted: missing OPENAI_API_KEY');
            return null;
        }

        // Remove source language from targets
        $targetLanguages = array_diff($targetLanguages, [$sourceLanguage]);
        
        if (empty($targetLanguages)) {
            return [];
        }

        $languageNames = $this->getLanguageNames();
        $prompt = $this->buildQuestionPrompt($questionData, $sourceLanguage, $targetLanguages, $languageNames);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator specializing in HR assessment questionnaires. You understand the critical difference between questions used for rating others versus self-assessment. Maintain the professional tone and assessment context while ensuring cultural and linguistic accuracy. '
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 1,
            ]);

            if (!$response->ok()) {
                Log::error('AI question translation API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Log::error('AI question translation: empty response content');
                return null;
            }

            $translations = json_decode($content, true);
            
            if (!is_array($translations) || !isset($translations['translations'])) {
                Log::error('AI question translation: invalid response format', ['content' => $content]);
                return null;
            }

            Log::info('AI question translation successful', [
                'source' => $sourceLanguage,
                'targets' => $targetLanguages,
                'question_preview' => substr($questionData['question'] ?? '', 0, 50)
            ]);

            return $translations['translations'];

        } catch (\Throwable $e) {
            Log::error('AI question translation exception', [
                'message' => $e->getMessage(),
                'source' => $sourceLanguage,
                'targets' => $targetLanguages
            ]);
            return null;
        }
    }

    /**
     * Build prompt for competency name and description translation
     */
    private function buildCompetencyNamePrompt(string $competencyName, ?string $competencyDescription, string $sourceLanguage, array $targetLanguages, array $languageNames): string
    {
        $sourceLanguageName = $languageNames[$sourceLanguage] ?? $sourceLanguage;
        $targetLanguagesList = array_map(fn($code) => $languageNames[$code] ?? $code, $targetLanguages);
        
        $hasDescription = !empty($competencyDescription);
        
        $prompt = "Translate the following competency information from {$sourceLanguageName} to " . implode(', ', $targetLanguagesList) . ".

**Original competency ({$sourceLanguageName}):**

**Name:** \"{$competencyName}\"";

        if ($hasDescription) {
            $prompt .= "\n**Description:** \"{$competencyDescription}\"";
        }

        $prompt .= "\n\n**Instructions:**
- This is a business/HR competency that will be used in employee assessments
- Maintain the professional meaning and business context
- Keep translations concise and clear for the name
- If description is provided, maintain its explanatory nature";

        if ($hasDescription) {
            $prompt .= "\n- The description should provide context and detail about the competency";
        }

        $prompt .= "\n- Adapt to each language's business terminology conventions
- Ensure translations sound natural to native speakers

**Required JSON format:**\n```json\n{
  \"translations\": {";

        // Build the JSON structure for each target language
        $languageEntries = [];
        foreach ($targetLanguages as $lang) {
            $entry = "\n    \"{$lang}\": {\n      \"name\": \"[translated name]\"";
            if ($hasDescription) {
                $entry .= ",\n      \"description\": \"[translated description]\"";
            }
            $entry .= "\n    }";
            $languageEntries[] = $entry;
        }

        $prompt .= implode(',', $languageEntries);
        $prompt .= "\n  }\n}\n```

Provide the translations in the exact JSON format above.";

        return $prompt;
    }

    /**
     * Build prompt for competency question translation
     */
    private function buildQuestionPrompt(array $questionData, string $sourceLanguage, array $targetLanguages, array $languageNames): string
    {
        $sourceLanguageName = $languageNames[$sourceLanguage] ?? $sourceLanguage;
        $targetLanguagesList = array_map(fn($code) => $languageNames[$code] ?? $code, $targetLanguages);
        
        return "Translate the following HR assessment question components from {$sourceLanguageName} to " . implode(', ', $targetLanguagesList) . ".

**Original question components ({$sourceLanguageName}):**

**Question (for rating others):** \"{$questionData['question']}\"
**Question for self-assessment:** \"{$questionData['question_self']}\"
**Minimum label:** \"{$questionData['min_label']}\"
**Maximum label:** \"{$questionData['max_label']}\"

**Critical Instructions:**
- The 'question' is used when rating OTHER people
- The 'question_self' is used for SELF-assessment 
- Maintain this important distinction in all translations
- Where possible, use the appropiate they/them neutral proverbs. Prioritize to keep the original meaning across languages.
- Keep the assessment/evaluation context intact
- Maintain professional HR terminology
- Ensure labels are appropriate for rating scales and to appear on a Likert-scale as endpoint labels.
- Adapt to each language's cultural context while preserving meaning

**Required JSON format:**
```json
{
  \"translations\": {
" . implode(",\n", array_map(function($lang) {
    return "    \"{$lang}\": {
      \"question\": \"[translation for rating others]\",
      \"question_self\": \"[translation for self-assessment]\",
      \"min_label\": \"[minimum scale label]\",
      \"max_label\": \"[maximum scale label]\"
    }";
}, $targetLanguages)) . "
  }
}
```

Provide the translations in the exact JSON format above.";
    }

    /**
     * Get language names mapping
     */
    private function getLanguageNames(): array
    {
        return [
            'hu' => 'Hungarian',
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'cs' => 'Czech',
            'sk' => 'Slovak',
            'ro' => 'Romanian',
            'bg' => 'Bulgarian',
            'hr' => 'Croatian',
            'sl' => 'Slovenian',
            'sr' => 'Serbian',
            'mk' => 'Macedonian',
            'sq' => 'Albanian',
            'el' => 'Greek',
            'tr' => 'Turkish',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'be' => 'Belarusian',
            'lt' => 'Lithuanian',
            'lv' => 'Latvian',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
            'is' => 'Icelandic',
        ];
    }

    /**
     * Translate CEO rank name to multiple languages
     * 
     * @param string $rankName The CEO rank name to translate
     * @param string $sourceLanguage Source language code (e.g., 'hu', 'en')
     * @param array $targetLanguages Array of target language codes
     * @return array|null Array of translations or null on failure
     */
    public function translateCeoRankName(string $rankName, string $sourceLanguage, array $targetLanguages): ?array
    {
        if (!$this->apiKey) {
            Log::warning('AI CEO rank translation aborted: missing OPENAI_API_KEY');
            return null;
        }

        // Remove source language from targets to avoid duplicates
        $targetLanguages = array_diff($targetLanguages, [$sourceLanguage]);
        
        if (empty($targetLanguages)) {
            return [];
        }

        $languageNames = $this->getLanguageNames();
        $prompt = $this->buildCeoRankPrompt($rankName, $sourceLanguage, $targetLanguages, $languageNames);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator specializing in business and HR terminology, particularly executive ranking and performance evaluation systems. You maintain the meaning and professional tone while adapting to each language\'s business context.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 1,
            ]);

            if (!$response->ok()) {
                Log::error('AI CEO rank translation API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Log::error('AI CEO rank translation: empty response content');
                return null;
            }

            $translations = json_decode($content, true);
            
            if (!is_array($translations) || !isset($translations['translations'])) {
                Log::error('AI CEO rank translation: invalid response format', ['content' => $content]);
                return null;
            }

            Log::info('AI CEO rank translation successful', [
                'source' => $sourceLanguage,
                'targets' => $targetLanguages,
                'original_name' => $rankName
            ]);

            return $translations['translations'];

        } catch (\Throwable $e) {
            Log::error('AI CEO rank translation exception', [
                'message' => $e->getMessage(),
                'rank_name' => $rankName,
                'source' => $sourceLanguage,
                'targets' => $targetLanguages
            ]);
            return null;
        }
    }

    /**
     * Build prompt for CEO rank name translation
     */
    private function buildCeoRankPrompt(string $rankName, string $sourceLanguage, array $targetLanguages, array $languageNames): string
    {
        $sourceLanguageName = $languageNames[$sourceLanguage] ?? $sourceLanguage;
        $targetLanguagesList = array_map(fn($code) => $languageNames[$code] ?? $code, $targetLanguages);
        
        $prompt = "Translate the following CEO/executive performance rank name from {$sourceLanguageName} to " . implode(', ', $targetLanguagesList) . ".

This is a performance evaluation rank used in corporate performance management systems. Maintain the professional, formal tone appropriate for executive assessment.

Original rank name: \"{$rankName}\"

Provide the translations in the following JSON format:
{
  \"translations\": {";

        foreach ($targetLanguages as $lang) {
            $langName = $languageNames[$lang] ?? $lang;
            $prompt .= "\n    \"{$lang}\": {\n      \"name\": \"[translated rank name in {$langName}]\"\n    },";
        }

        $prompt = rtrim($prompt, ',') . "\n  }\n}";

        $prompt .= "\n\nGuidelines:
- Maintain the formal, professional tone appropriate for executive performance rankings
- Keep the translation concise and clear
- Ensure the translation conveys the same level of performance/achievement as the original
- Use terminology commonly used in business performance evaluations in each target language
- The translation should be appropriate for corporate HR and management contexts";

        return $prompt;
    }
}