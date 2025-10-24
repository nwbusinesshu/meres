<?php

namespace App\Services;

use App\Models\HelpChatSession;
use App\Models\HelpChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HelpChatService
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
     * Send a message to ChatGPT and get a response
     * 
     * @param string $message User's message
     * @param HelpChatSession $session The chat session
     * @param string|null $additionalDocs Additional help documents loaded via function calling
     * @return array|null Response array with message and session info, or null on failure
     */
    public function sendMessage(string $message, HelpChatSession $session, ?string $additionalDocs = null): ?array
    {
        if (!$this->apiKey) {
            Log::warning('Help chat aborted: missing OPENAI_API_KEY');
            return null;
        }

        // Load full conversation history from database
        $chatHistory = $session->orderedMessages()
            ->get()
            ->map(function($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content
                ];
            })
            ->toArray();

        $context = [
            'view_key' => $session->view_key,
            'role' => session('org_role') ?? $session->user->type ?? 'guest',
            'locale' => $session->locale,
            'org_id' => session('org_id'), // ✅ NEW: Add org_id for config loading
        ];

        // Build system prompt with context
        $systemPrompt = $this->buildSystemPrompt($context, $additionalDocs);
        
        // Build full message history
        $messages = $this->buildMessageHistory($systemPrompt, $chatHistory, $message);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            if (!$response->ok()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Log::error('Help chat: empty response content');
                return null;
            }

            // Save both user message and AI response to database
            // If additionalDocs is set, user message was already saved, so only save assistant response
            if (!$additionalDocs) {
                $this->saveMessageToDatabase($session, 'user', $message);
            }
            $this->saveMessageToDatabase($session, 'assistant', $content);

            // Update session last_message_at
            $session->touchLastMessage();

            // Generate title if this is the first message
            $messageCount = $session->messages()->count();
            if ($messageCount === 2 || ($additionalDocs && $messageCount === 3)) {
                $this->generateAndSaveTitle($session, $message);
            }

            Log::info('Help chat successful', [
                'session_id' => $session->id,
                'view_key' => $session->view_key,
                'message_length' => strlen($message),
                'response_length' => strlen($content),
                'had_additional_docs' => !is_null($additionalDocs)
            ]);

            return [
                'message' => $content,
                'timestamp' => now()->toIso8601String(),
                'model' => $this->model,
                'session_id' => $session->id
            ];

        } catch (\Throwable $e) {
            Log::error('Help chat exception', [
                'message' => $e->getMessage(),
                'session_id' => $session->id
            ]);
            return null;
        }
    }

    /**
     * Load additional help documents (called from controller after function call request)
     * 
     * @param array $viewKeys Array of view keys to load
     * @param string $locale User's locale
     * @param string|null $userRole User's role for filtering
     * @return string Combined help documents
     */
    public function loadAdditionalHelpDocuments(array $viewKeys, string $locale, ?string $userRole = null): string
    {
        return $this->loadMultipleHelpDocuments($viewKeys, $locale, $userRole);
    }

    /**
     * Generate a conversation title based on the first user question
     */
    public function generateAndSaveTitle(HelpChatSession $session, string $firstMessage): void
    {
        try {
            $prompt = "Based on this user question, generate a short, descriptive title (max 5 words) for this conversation. Just return the title, nothing else.\n\nQuestion: {$firstMessage}";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You generate short, clear titles for conversations. Maximum 5 words. No quotes or punctuation at the end.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.5,
                'max_tokens' => 20,
            ]);

            if ($response->ok()) {
                $data = $response->json();
                $title = $data['choices'][0]['message']['content'] ?? null;
                
                if ($title) {
                    // Clean up the title
                    $title = trim($title, '"\'.,;: ');
                    $title = substr($title, 0, 255); // Ensure it fits in DB column
                    
                    $session->title = $title;
                    $session->save();

                    Log::info('Conversation title generated', [
                        'session_id' => $session->id,
                        'title' => $title
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to generate conversation title', [
                'session_id' => $session->id,
                'error' => $e->getMessage()
            ]);
            // Don't fail the whole request if title generation fails
        }
    }

    /**
     * Save a message to the database
     */
    private function saveMessageToDatabase(HelpChatSession $session, string $role, string $content): void
    {
        HelpChatMessage::create([
            'session_id' => $session->id,
            'role' => $role,
            'content' => $content
        ]);
    }

    /**
     * Load the global help index
     * 
     * @param string $locale
     * @return string
     */
    private function loadGlobalIndex(string $locale): string
    {
        $filePath = resource_path("help/{$locale}/_global/index.md");
        
        if (!file_exists($filePath)) {
            Log::warning("Global help index not found", ['locale' => $locale]);
            return "Global help index not available.";
        }
        
        try {
            $content = file_get_contents($filePath);
            
            // Parse and extract only the content (remove YAML front matter if exists)
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
                return trim($matches[2]);
            }
            
            return trim($content);
        } catch (\Exception $e) {
            Log::error("Failed to load global help index", [
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return "Global help index could not be loaded.";
        }
    }

    /**
     * Load a specific help document
     * 
     * @param string $viewKey
     * @param string $locale
     * @param string|null $userRole For role-based content filtering
     * @return string
     */
    private function loadHelpDocument(string $viewKey, string $locale, ?string $userRole = null): string
    {
        $filePath = resource_path("help/{$locale}/{$viewKey}.md");
        
        if (!file_exists($filePath)) {
            Log::warning("Help document not found", [
                'view_key' => $viewKey,
                'locale' => $locale
            ]);
            return "Help document for '{$viewKey}' not available.";
        }
        
        try {
            $content = file_get_contents($filePath);
            
            // Parse YAML front matter and content
            $parsed = $this->parseMarkdownWithFrontMatter($content);
            
            // Filter content based on user role if specified
            if ($userRole) {
                $parsed['content'] = $this->filterContentByRole($parsed['content'], $userRole);
            }
            
            // Return metadata + content formatted for AI
            $formattedContent = "=== HELP DOCUMENT: {$viewKey} ===\n";
            
            if (!empty($parsed['metadata'])) {
                $formattedContent .= "Metadata:\n";
                foreach ($parsed['metadata'] as $key => $value) {
                    if (is_array($value)) {
                        $formattedContent .= "  {$key}: " . implode(', ', $value) . "\n";
                    } else {
                        $formattedContent .= "  {$key}: {$value}\n";
                    }
                }
                $formattedContent .= "\n";
            }
            
            $formattedContent .= $parsed['content'];
            
            return $formattedContent;
            
        } catch (\Exception $e) {
            Log::error("Failed to load help document", [
                'view_key' => $viewKey,
                'locale' => $locale,
                'error' => $e->getMessage()
            ]);
            return "Help document for '{$viewKey}' could not be loaded.";
        }
    }

    /**
     * Load multiple help documents
     * 
     * @param array $viewKeys
     * @param string $locale
     * @param string|null $userRole
     * @return string
     */
    private function loadMultipleHelpDocuments(array $viewKeys, string $locale, ?string $userRole = null): string
    {
        $combinedContent = "";
        
        foreach ($viewKeys as $viewKey) {
            $doc = $this->loadHelpDocument($viewKey, $locale, $userRole);
            $combinedContent .= $doc . "\n\n";
        }
        
        return $combinedContent;
    }

    /**
     * Parse markdown file with YAML front matter
     * 
     * @param string $content
     * @return array ['metadata' => array, 'content' => string]
     */
    private function parseMarkdownWithFrontMatter(string $content): array
    {
        $metadata = [];
        $markdownContent = $content;

        // Check if file starts with YAML front matter (---)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $yamlContent = $matches[1];
            $markdownContent = $matches[2];

            // Parse YAML manually (simple key-value parser)
            $lines = explode("\n", $yamlContent);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Handle simple key: value pairs
                if (preg_match('/^([a-zA-Z_]+):\s*(.*)$/', $line, $match)) {
                    $key = $match[1];
                    $value = trim($match[2]);

                    // Handle arrays like [item1, item2]
                    if (preg_match('/^\[(.*)\]$/', $value, $arrayMatch)) {
                        $items = explode(',', $arrayMatch[1]);
                        $value = array_map('trim', $items);
                    }

                    $metadata[$key] = $value;
                }
            }
        }

        return [
            'metadata' => $metadata,
            'content' => trim($markdownContent)
        ];
    }

    /**
     * Filter content based on user role using <role-visibility> tags
     * 
     * @param string $content
     * @param string $userRole
     * @return string
     */
    private function filterContentByRole(string $content, string $userRole): string
    {
        $pattern = '/<role-visibility roles="([^"]+)">(.+?)<\/role-visibility>/s';
        
        $filtered = preg_replace_callback($pattern, function($matches) use ($userRole) {
            $allowedRoles = array_map('trim', explode(',', $matches[1]));
            
            // Check if 'all' or user's role is in allowed roles
            if (in_array('all', $allowedRoles) || in_array($userRole, $allowedRoles)) {
                return $matches[2]; // Keep the content
            }
            
            return ''; // Remove the content
        }, $content);
        
        return $filtered;
    }

    /**
     * ✅ NEW: Get human-readable organization config summary
     * 
     * @param int $orgId
     * @return string
     */
    private function getOrgConfigSummary(int $orgId): string
    {
        try {
            /** @var ThresholdService $thresholdService */
            $thresholdService = app(ThresholdService::class);
            $config = $thresholdService->getOrgConfigMap($orgId);
            
            $summary = "=== ORGANIZATION CONFIGURATION ===\n\n";
            
            // Threshold Method
            $thresholdMode = strtoupper($config['threshold_mode'] ?? 'FIXED');
            $summary .= "**Threshold Method:** {$thresholdMode}\n";
            
            switch (strtolower($config['threshold_mode'])) {
                case 'fixed':
                    $summary .= "  - Level Up Threshold: {$config['normal_level_up']} points\n";
                    $summary .= "  - Level Down Threshold: {$config['normal_level_down']} points\n";
                    $summary .= "  - Monthly Level Down: {$config['monthly_level_down']} points\n";
                    $summary .= "  - This means fixed thresholds are used for all assessments\n";
                    break;
                    
                case 'hybrid':
                    $summary .= "  - Minimum Absolute Up: {$config['threshold_min_abs_up']} points\n";
                    $summary .= "  - Top Percentage: {$config['threshold_top_pct']}%\n";
                    $summary .= "  - Gap Minimum: {$config['threshold_gap_min']} points\n";
                    $summary .= "  - Grace Points: {$config['threshold_grace_points']} points\n";
                    $summary .= "  - This combines fixed minimum with relative top % calculation\n";
                    break;
                    
                case 'dynamic':
                    $summary .= "  - Top Percentage: {$config['threshold_top_pct']}%\n";
                    $summary .= "  - Bottom Percentage: {$config['threshold_bottom_pct']}%\n";
                    $summary .= "  - This means thresholds are calculated based on score distribution\n";
                    break;
                    
                case 'suggested':
                    $promoRate = ((float)$config['target_promo_rate_max'] * 100);
                    $demoRate = ((float)$config['target_demotion_rate_max'] * 100);
                    $summary .= "  - Target Promotion Rate: {$promoRate}%\n";
                    $summary .= "  - Target Demotion Rate: {$demoRate}%\n";
                    $summary .= "  - Use Telemetry Trust: " . ($config['use_telemetry_trust'] === '1' ? 'Yes' : 'No') . "\n";
                    $summary .= "  - This means AI suggests thresholds based on policy and historical data\n";
                    break;
            }
            
            $summary .= "\n**Feature Toggles:**\n";
            
            // Multi-level departments
            $multiLevel = $config['enable_multi_level'] === '1';
            $summary .= "  - Multi-Level Departments: " . ($multiLevel ? 'ENABLED' : 'DISABLED') . "\n";
            if ($multiLevel) {
                $summary .= "    → Users will see hierarchical department structure with multiple management levels, can create departments, assign managers, employees to departments.\n";
            } else {
                $summary .= "    → Only single-level departments are available - but the user can still create subordinate or superior relations, so there can be 'manager-like' employees in the relations who have subordinates and superiors too.\n";
            }
            
            // Bonus calculation
            $bonusCalc = $config['enable_bonus_calculation'] === '1';
            $summary .= "  - Bonus Calculation: " . ($bonusCalc ? 'ENABLED' : 'DISABLED') . "\n";
            if ($bonusCalc) {
                $summary .= "    → Monetary bonus amounts are calculated and displayed based on performance levels\n";
            } else {
                $summary .= "    → Only performance levels are shown, no monetary calculations\n";
            }
            
            // Show bonus malus
            $showBM = $config['show_bonus_malus'] === '1';
            $summary .= "  - Show Bonus/Malus Levels: " . ($showBM ? 'ENABLED' : 'DISABLED') . "\n";
            if ($showBM) {
                $summary .= "    → Performance levels (M04-B10) are visible to relevant users\n";
            }
            
            // Employees see bonuses
            $empSeeBonuses = $config['employees_see_bonuses'] === '1';
            $summary .= "  - Employees See Bonuses: " . ($empSeeBonuses ? 'ENABLED' : 'DISABLED') . "\n";
            if ($empSeeBonuses) {
                $summary .= "    → Regular employees can view their own bonus calculations\n";
            } else {
                $summary .= "    → Only managers and admins can view bonus calculations\n";
            }
            
            // Easy relation setup
            $easySetup = $config['easy_relation_setup'] === '1';
            $summary .= "  - Easy Relation Setup: " . ($easySetup ? 'ENABLED' : 'DISABLED') . "\n";
            if ($easySetup) {
                $summary .= "    → Simplified workflow for setting up colleague relationships. When it is turned on, all the relations set by the admin user automatically gets it's opposite direction relation set. No need to double work.\n";
            }
            
            // AI Telemetry
            $aiTelemetry = $config['ai_telemetry_enabled'] === '1';
            $strictAnon = $config['strict_anonymous_mode'] === '1';
            $summary .= "  - AI Telemetry: " . ($aiTelemetry ? 'ENABLED' : 'DISABLED') . "\n";
            if ($strictAnon) {
                $summary .= "    → Strict Anonymous Mode is ON (AI telemetry, suggested threshold method is disabled for privacy)\n";
            } elseif ($aiTelemetry) {
                $summary .= "    → Behavioral data is collected for fraud detection and AI-powered insights, threshold can be calculated via the help of the AI assistant.\n";
            }
            
            // OAuth 2FA
            $forceOauth = $config['force_oauth_2fa'] === '1';
            $summary .= "  - Force OAuth 2FA: " . ($forceOauth ? 'ENABLED' : 'DISABLED') . "\n";
            if ($forceOauth) {
                $summary .= "    → Users must use 2FA with OAuth (Google/Microsoft) login too. If turned off, only password login requires email 2FA code validation.\n";
            }
            
            // Translation languages
            if (!empty($config['translation_languages'])) {
                $langs = json_decode($config['translation_languages'], true);
                if (is_array($langs) && count($langs) > 0) {
                    $summary .= "The admin user can select multiple languages to translate the competencies to (if they have a multilingual organization model.\n**Translation Languages:** " . implode(', ', array_map('strtoupper', $langs)) . "\n";
                }
            }
            
            $summary .= "\n---\nUse this configuration context to provide accurate, context-aware help to users.\n";
            
            return $summary;
            
        } catch (\Exception $e) {
            Log::error('Failed to load org config for help chat', [
                'org_id' => $orgId,
                'error' => $e->getMessage()
            ]);
            return "Organization configuration could not be loaded.\n";
        }
    }

    /**
     * Build the system prompt with enhanced context
     * 
     * ✅ UPDATED: Now includes organization configuration for context-aware help
     * 
     * @param array $context
     * @param string|null $additionalDocs Additional help documents loaded via function calling
     * @return string
     */
    private function buildSystemPrompt(array $context, ?string $additionalDocs = null): string
    {
        $viewKey = $context['view_key'] ?? 'unknown';
        $role = $context['role'] ?? 'guest';
        $locale = $context['locale'] ?? 'en';
        $orgId = $context['org_id'] ?? null;

        $prompt = "You are a helpful assistant for an HR assessment application called Quarma360. ";
        $prompt .= "You help users understand features and navigate the system.\n\n";
        
        $prompt .= "CURRENT CONTEXT:\n";
        $prompt .= "- Current Page: {$viewKey}\n";
        $prompt .= "- User Role: {$role}\n";
        $prompt .= "- Language: {$locale}\n\n";
        
        // ✅ NEW: Add organization configuration context
        if ($orgId) {
            $prompt .= $this->getOrgConfigSummary($orgId);
            $prompt .= "\n";
        }
        
        // Load global index (always)
        $globalIndex = $this->loadGlobalIndex($locale);
        $prompt .= "=== GLOBAL HELP INDEX ===\n";
        $prompt .= $globalIndex . "\n\n";
        
        // Load current page help (always)
        $currentPageHelp = $this->loadHelpDocument($viewKey, $locale, $role);
        $prompt .= "=== CURRENT PAGE HELP ===\n";
        $prompt .= $currentPageHelp . "\n\n";
        
        // Add additional documents if loaded via function calling
        if ($additionalDocs) {
            $prompt .= "=== ADDITIONAL HELP DOCUMENTS ===\n";
            $prompt .= $additionalDocs . "\n\n";
        }
        
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "- Use the help documents AND organization configuration above to provide accurate, detailed answers\n";
        $prompt .= "- Tailor your responses based on the organization's enabled features and settings\n";
        $prompt .= "- If a feature is disabled in the organization, explain that it's not available for them\n";
        $prompt .= "- If the user's question relates to a different page, you can request additional help documents using the load_help_documents function\n";
        $prompt .= "- Be helpful, friendly, and concise, but do not answer questions out of the scope of the app - if they try to kindly ignore the question and tell them to ask about the app - DO NOT answer any sexually oriented question, propaganda, assaults, bullying, cheating, drugs, weapons, erotic content, cursing\n";
        $prompt .= "- Provide practical step-by-step guidance when appropriate\n";
        $prompt .= "- If information is in a different section, guide users on how to navigate there\n";
        $prompt .= "- Keep responses clear and easy to understand - always keep in mind the user's role and only talk about topics related to them and from their point of view\n";
        $prompt .= "- Respond in " . ($locale === 'hu' ? 'Hungarian' : 'English') . "\n";
        $prompt .= "- Do not use technical page names, button names (like 'admin.results') - always use the translated names\n";
        $prompt .= "- Remember context from previous messages in this conversation\n";

        return $prompt;
    }

    /**
     * Build the full message history array for the API
     */
    private function buildMessageHistory(string $systemPrompt, array $chatHistory, string $currentMessage): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ]
        ];

        // Add ALL previous chat history (no limit - AI needs full context)
        foreach ($chatHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? ''
            ];
        }

        // Add current message
        $messages[] = [
            'role' => 'user',
            'content' => $currentMessage
        ];

        return $messages;
    }
}