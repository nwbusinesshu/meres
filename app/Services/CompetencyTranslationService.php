<?php

namespace App\Services;

use App\Models\Competency;
use App\Models\CompetencyQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompetencyTranslationService
{
    /**
     * Translate a competency name to multiple languages using AI
     */
    public static function translateCompetencyName(Competency $competency, array $targetLanguages): ?array
    {
        $originalLanguage = $competency->original_language ?? 'hu';
        $competencyName = $competency->getTranslatedName($originalLanguage);

        if (empty($competencyName)) {
            Log::warning('CompetencyTranslation: Empty competency name', ['id' => $competency->id]);
            return null;
        }

        $prompt = self::buildCompetencyNamePrompt($competencyName, $originalLanguage, $targetLanguages);
        $schema = self::buildCompetencyNameSchema($targetLanguages);

        return self::callOpenAI($prompt, $schema, [
            'type' => 'competency_name',
            'competency_id' => $competency->id,
            'original_language' => $originalLanguage
        ]);
    }

    /**
     * Translate all fields of a competency question to multiple languages using AI
     */
    public static function translateCompetencyQuestion(CompetencyQuestion $question, array $targetLanguages): ?array
    {
        $originalLanguage = $question->original_language ?? 'hu';
        
        $originalData = [
            'question' => $question->getTranslatedQuestion($originalLanguage),
            'question_self' => $question->getTranslatedQuestionSelf($originalLanguage),
            'min_label' => $question->getTranslatedMinLabel($originalLanguage),
            'max_label' => $question->getTranslatedMaxLabel($originalLanguage),
        ];

        // Validate original data
        foreach ($originalData as $field => $value) {
            if (empty($value)) {
                Log::warning('CompetencyTranslation: Empty question field', [
                    'id' => $question->id,
                    'field' => $field
                ]);
                return null;
            }
        }

        $prompt = self::buildQuestionPrompt($originalData, $originalLanguage, $targetLanguages);
        $schema = self::buildQuestionSchema($targetLanguages);

        return self::callOpenAI($prompt, $schema, [
            'type' => 'competency_question',
            'question_id' => $question->id,
            'competency_id' => $question->competency_id,
            'original_language' => $originalLanguage
        ]);
    }

    /**
     * Build prompt for competency name translation
     */
    private static function buildCompetencyNamePrompt(string $name, string $fromLang, array $toLangs): string
    {
        $langNames = LanguageService::getLanguageNames();
        $fromLangName = $langNames[$fromLang] ?? $fromLang;
        $toLangNames = array_map(fn($lang) => $langNames[$lang] ?? $lang, $toLangs);

        return "You are translating competency names for a professional assessment system. 

Please translate the following competency name from {$fromLangName} to " . implode(', ', $toLangNames) . ":

Original competency name ({$fromLang}): \"{$name}\"

Requirements:
- Maintain professional terminology
- Keep the meaning precise and business-appropriate
- Ensure translations are suitable for workplace competency assessments
- Use formal language appropriate for HR and business contexts

Return the translations in the exact JSON format specified.";
    }

    /**
     * Build prompt for competency question translation
     */
    private static function buildQuestionPrompt(array $originalData, string $fromLang, array $toLangs): string
    {
        $langNames = LanguageService::getLanguageNames();
        $fromLangName = $langNames[$fromLang] ?? $fromLang;
        $toLangNames = array_map(fn($lang) => $langNames[$lang] ?? $lang, $toLangs);

        return "You are translating competency assessment questions for a professional evaluation system.

Please translate the following competency question components from {$fromLangName} to " . implode(', ', $toLangNames) . ":

Original question for rating others ({$fromLang}): \"{$originalData['question']}\"
Original question for self-rating ({$fromLang}): \"{$originalData['question_self']}\"
Minimum scale label ({$fromLang}): \"{$originalData['min_label']}\"
Maximum scale label ({$fromLang}): \"{$originalData['max_label']}\"

Requirements:
- 'question': For rating other people (3rd person perspective)
- 'question_self': For self-rating (1st person perspective)
- Maintain the grammatical perspective difference between the two question types
- Keep professional assessment terminology
- Ensure scale labels are appropriate and consistent
- Use formal language suitable for workplace evaluations

Return the translations in the exact JSON format specified.";
    }

    /**
     * Build JSON schema for competency name translation
     */
    private static function buildCompetencyNameSchema(array $targetLanguages): array
    {
        $properties = [];
        foreach ($targetLanguages as $lang) {
            $properties[$lang] = [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 255
            ];
        }

        return [
            'name' => 'CompetencyNameTranslation',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => $properties,
                'required' => $targetLanguages,
            ],
            'strict' => true,
        ];
    }

    /**
     * Build JSON schema for competency question translation
     */
    private static function buildQuestionSchema(array $targetLanguages): array
    {
        $langProperties = [];
        foreach ($targetLanguages as $lang) {
            $langProperties[$lang] = [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'question' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                    'question_self' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                    'min_label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'max_label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                ],
                'required' => ['question', 'question_self', 'min_label', 'max_label'],
            ];
        }

        return [
            'name' => 'CompetencyQuestionTranslation',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => $langProperties,
                'required' => $targetLanguages,
            ],
            'strict' => true,
        ];
    }

    /**
     * Call OpenAI API - reusing the structure from TelemetryService
     */
    protected static function callOpenAI(string $prompt, array $jsonSchema, array $meta): ?array
    {
        $apiKey = (string) config('services.openai.key', env('OPENAI_API_KEY'));
        $model = (string) env('OPENAI_MODEL', 'gpt-4.1-mini');
        $timeout = (int) env('OPENAI_TIMEOUT', 12);

        if (!$apiKey) {
            Log::warning('[CompetencyTranslation] callOpenAI: missing API key');
            return null;
        }

        $idempotencyKey = 'competency_translation:' . ($meta['competency_id'] ?? '') . ':' . Str::uuid()->toString();

        Log::info('[CompetencyTranslation] callOpenAI start', [
            'model' => $model,
            'timeout' => $timeout,
            'idempotency' => $idempotencyKey,
            'type' => $meta['type'] ?? 'unknown'
        ]);

        try {
            $resp = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'input' => $prompt,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $jsonSchema['name'] ?? 'CompetencyTranslation',
                            'schema' => $jsonSchema['schema'] ?? [],
                            'strict' => $jsonSchema['strict'] ?? true,
                        ],
                    ],
                ]);

            $status = $resp->status();
            Log::info('[CompetencyTranslation] callOpenAI http', ['status' => $status]);

            if (!$resp->ok()) {
                $bodyPreview = substr($resp->body(), 0, 4000);
                Log::warning('[CompetencyTranslation] callOpenAI not ok', [
                    'status' => $status, 
                    'body' => $bodyPreview
                ]);
                return null;
            }

            $data = $resp->json();

            // Extract structured response
            $structured = null;

            if (isset($data['output_text']) && is_string($data['output_text'])) {
                $maybe = json_decode($data['output_text'], true);
                if (is_array($maybe)) $structured = $maybe;
            }

            if (!$structured && isset($data['output']) && is_array($data['output'])) {
                $first = $data['output'][0]['content'][0] ?? null;
                if ($first && isset($first['text'])) {
                    $maybe = json_decode($first['text'], true);
                    if (is_array($maybe)) $structured = $maybe;
                }
            }

            if ($structured) {
                Log::info('[CompetencyTranslation] callOpenAI success', [
                    'languages' => array_keys($structured),
                    'type' => $meta['type'] ?? 'unknown'
                ]);
                return $structured;
            } else {
                Log::warning('[CompetencyTranslation] callOpenAI: could not extract structured response', [
                    'raw_keys' => array_keys($data)
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error('[CompetencyTranslation] callOpenAI exception', [
                'message' => $e->getMessage(),
                'type' => $meta['type'] ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Apply translations to a competency
     */
    public static function applyCompetencyTranslations(Competency $competency, array $translations): void
    {
        foreach ($translations as $language => $translation) {
            if (LanguageService::isValidLanguage($language) && !empty($translation)) {
                $competency->setTranslation($language, $translation);
            }
        }
        $competency->save();
    }

    /**
     * Apply translations to a competency question
     */
    public static function applyQuestionTranslations(CompetencyQuestion $question, array $translations): void
    {
        foreach ($translations as $language => $languageData) {
            if (LanguageService::isValidLanguage($language) && is_array($languageData)) {
                $question->setTranslation($language, $languageData);
            }
        }
        $question->save();
    }
}