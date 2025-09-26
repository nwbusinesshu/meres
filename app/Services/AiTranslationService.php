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
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model = env('OPENAI_MODEL', 'gpt-4o-mini');
        $this->timeout = (int) env('OPENAI_TIMEOUT', 30);
    }

    /**
     * Translate competency name to multiple languages
     * 
     * @param string $competencyName The competency name to translate
     * @param string $sourceLanguage Source language code (e.g., 'hu', 'en')
     * @param array $targetLanguages Array of target language codes
     * @return array|null Array of translations or null on failure
     */
    public function translateCompetencyName(string $competencyName, string $sourceLanguage, array $targetLanguages): ?array
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
        $prompt = $this->buildCompetencyNamePrompt($competencyName, $sourceLanguage, $targetLanguages, $languageNames);

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
                'temperature' => 0.3,
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
                'original' => $competencyName
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
                        'content' => 'You are a professional translator specializing in HR assessment questionnaires. You understand the critical difference between questions used for rating others versus self-assessment. Maintain the professional tone and assessment context while ensuring cultural and linguistic accuracy.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'response_format' => [
                    'type' => 'json_object'
                ],
                'temperature' => 0.3,
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
     * Build prompt for competency name translation
     */
    private function buildCompetencyNamePrompt(string $competencyName, string $sourceLanguage, array $targetLanguages, array $languageNames): string
    {
        $sourceLanguageName = $languageNames[$sourceLanguage] ?? $sourceLanguage;
        $targetLanguagesList = array_map(fn($code) => $languageNames[$code] ?? $code, $targetLanguages);
        
        return "Translate the following competency name from {$sourceLanguageName} to " . implode(', ', $targetLanguagesList) . ".

**Original competency name ({$sourceLanguageName}):**
\"{$competencyName}\"

**Instructions:**
- This is a business/HR competency name that will be used in employee assessments
- Maintain the professional meaning and business context
- Keep translations concise and clear
- Adapt to each language's business terminology conventions
- Ensure translations sound natural to native speakers

**Required JSON format:**
```json
{
  \"translations\": {
    \"" . implode("\": \"[translation]\",\n    \"", $targetLanguages) . "\": \"[translation]\"
  }
}
```

Provide the translations in the exact JSON format above.";
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
- Keep the assessment/evaluation context intact
- Maintain professional HR terminology
- Ensure labels are appropriate for rating scales
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
            'ro' => 'Romanian',
            'sk' => 'Slovak',
            'cs' => 'Czech',
            'pl' => 'Polish',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'da' => 'Danish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
        ];
    }
}