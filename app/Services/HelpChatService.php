<?php

namespace App\Services;

use App\Models\HelpChatSession;
use App\Models\HelpChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


class HelpChatService
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model = env('OPENAI_MODEL_CHAT', 'gpt-5-nano');
        $this->timeout = (int) env('OPENAI_TIMEOUT', 30);
    }

    /**
     * Send a message to ChatGPT and get a response
     * 
     * ✅ FIXED: Now properly includes tools parameter for function calling
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
            'org_id' => session('org_id'),
        ];

        // Build system prompt with context
        $systemPrompt = $this->buildSystemPrompt($context, $additionalDocs);
        
        // Build full message history
        $messages = $this->buildMessageHistory($systemPrompt, $chatHistory, $message);

        // ✅ FIXED: Define tools for function calling
        $tools = [];
        
        // Only add the tool if we're not already loading additional docs
        if (!$additionalDocs) {
            $tools = [
                [
                    'type' => 'function',
                    'function' => [
                        'name' => 'load_help_documents',
                        'description' => 'Load additional help documents for other pages when the user asks about features or functionality not covered in the current page help. Use this when you need information from other sections of the application to provide a complete answer.',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'view_keys' => [
                                    'type' => 'array',
                                    'description' => 'Array of view keys (page identifiers) to load help documents for. Examples: "admin.home", "admin.competencies", "admin.users", "employee.assessment", "results"',
                                    'items' => [
                                        'type' => 'string'
                                    ],
                                    'minItems' => 1,
                                    'maxItems' => 5
                                ],
                                'reason' => [
                                    'type' => 'string',
                                    'description' => 'Brief explanation of why these documents are needed to answer the user\'s question'
                                ]
                            ],
                            'required' => ['view_keys', 'reason']
                        ]
                    ]
                ]
            ];
        }

        try {
            // ✅ FIXED: Build API request with tools parameter
            $apiRequest = [
                'model' => $this->model,
                'messages' => $messages,
                'max_tokens' => 2000,
            ];
            
            // Add tools only if available
            if (!empty($tools)) {
                $apiRequest['tools'] = $tools;
                $apiRequest['tool_choice'] = 'auto'; // Let AI decide when to use tools
            }

            Log::info('Help chat API request', [
                'session_id' => $session->id,
                'message_count' => count($messages),
                'has_tools' => !empty($tools),
                'system_prompt_length' => strlen($systemPrompt)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post('https://api.openai.com/v1/chat/completions', $apiRequest);

            if (!$response->ok()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            
            // ✅ FIXED: Check for tool calls in response
            $choice = $data['choices'][0] ?? null;
            if (!$choice) {
                Log::error('Help chat: no choices in API response');
                return null;
            }

            $aiMessage = $choice['message'] ?? null;
            if (!$aiMessage) {
                Log::error('Help chat: no message in choice');
                return null;
            }

            // ✅ FIXED: Detect tool calls (new format)
            if (isset($aiMessage['tool_calls']) && is_array($aiMessage['tool_calls']) && count($aiMessage['tool_calls']) > 0) {
                $toolCall = $aiMessage['tool_calls'][0]; // Get first tool call
                
                if ($toolCall['function']['name'] === 'load_help_documents') {
                    $arguments = json_decode($toolCall['function']['arguments'], true);
                    
                    Log::info('Help chat: AI requested to load documents', [
                        'session_id' => $session->id,
                        'view_keys' => $arguments['view_keys'] ?? [],
                        'reason' => $arguments['reason'] ?? 'not specified'
                    ]);
                    
                    // Return function call request to controller
                    return [
                        'function_call' => [
                            'name' => 'load_help_documents',
                            'arguments' => $arguments
                        ],
                        'session_id' => $session->id
                    ];
                }
            }

            // Normal text response
            $content = $aiMessage['content'] ?? null;
            
            if (!$content) {
                Log::error('Help chat: empty response content', [
                    'ai_message' => $aiMessage
                ]);
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
                'session_id' => $session->id,
                'trace' => $e->getTraceAsString()
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
            /** @var \App\Services\ThresholdService $thresholdService */
            $thresholdService = app(\App\Services\ThresholdService::class);
            $config = $thresholdService->getOrgConfigMap($orgId);
            
            $summary = "=== ORGANIZATION CONFIGURATION ===\n\n";
            
            // Threshold Method
            $thresholdMode = strtoupper($config['threshold_mode'] ?? 'NORMAL');
            $summary .= "**Threshold Method:** {$thresholdMode}\n";
            
            if ($thresholdMode === 'NORMAL') {
                $summary .= "  - Thresholds are set manually by admins\n";
                $summary .= "  - Fixed percentile ranges for level changes\n";
            } elseif ($thresholdMode === 'SUGGESTED') {
                $summary .= "  - AI-suggested thresholds based on performance distribution\n";
                $summary .= "  - System recommends optimal cutoff points\n";
            }
            
            // Multi-level system
            if (!empty($config['enable_multi_level'])) {
                $summary .= "\n**Multi-Level System:** ENABLED\n";
                $summary .= "  - Employees have hierarchical levels (0, 1, 2, etc.)\n";
                $summary .= "  - Performance affects promotions and demotions\n";
            } else {
                $summary .= "\n**Multi-Level System:** DISABLED\n";
                $summary .= "  - No level tracking\n";
            }
            
            // Bonus/Malus
            if (!empty($config['show_bonus_malus'])) {
                $summary .= "\n**Bonus/Malus:** VISIBLE\n";
                if (!empty($config['enable_bonus_calculation'])) {
                    $summary .= "  - Bonus calculation: ENABLED\n";
                    $summary .= "  - Admins can calculate and manage bonuses\n";
                }
                if (!empty($config['employees_see_bonuses'])) {
                    $summary .= "  - Employees can see their bonuses\n";
                }
            } else {
                $summary .= "\n**Bonus/Malus:** HIDDEN\n";
            }
            
            // Anonymity
            if (!empty($config['strict_anon'])) {
                $summary .= "\n**Anonymity:** STRICT MODE\n";
                $summary .= "  - Evaluations are completely anonymous\n";
                $summary .= "  - AI telemetry automatically disabled\n";
            } else {
                $summary .= "\n**Anonymity:** STANDARD\n";
            }
            
            // AI Telemetry
            if (!empty($config['ai_telemetry'])) {
                $summary .= "\n**AI Telemetry:** ENABLED\n";
                $summary .= "  - System tracks evaluation patterns\n";
                $summary .= "  - Trust scores calculated\n";
            } else {
                $summary .= "\n**AI Telemetry:** DISABLED\n";
            }
            
            // 2FA
            if (!empty($config['force_oauth_2fa'])) {
                $summary .= "\n**Two-Factor Authentication:** ENFORCED FOR ALL LOGINS\n";
                $summary .= "If turned on, all login methods require email 2FA code validation.\n";
            } else {
                $summary .= "\n**Two-Factor Authentication:** PASSWORD LOGIN ONLY\n";
                $summary .= "If turned off, only password login requires email 2FA code validation.\n";
            }
            
            // Translation languages
            if (!empty($config['translation_languages'])) {
                $langs = json_decode($config['translation_languages'], true);
                if (is_array($langs) && count($langs) > 0) {
                    $summary .= "\n**Translation Languages:** " . implode(', ', array_map('strtoupper', $langs)) . "\n";
                    $summary .= "The admin user can select multiple languages to translate the competencies to (if they have a multilingual organization model).\n";
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
     * ✅ NEW: Get assessment status information
     * 
     * @return string
     */
    private function getAssessmentStatus(): string
    {
        try {
            $isRunning = \App\Services\AssessmentService::isAssessmentRunning();
            
            $summary = "=== ASSESSMENT STATUS ===\n\n";
            
            if ($isRunning) {
                $assessment = \App\Services\AssessmentService::getCurrentAssessment();
                
                if ($assessment) {
                    $summary .= "**Status:** ASSESSMENT IN PROGRESS ⚠️\n\n";
                    $summary .= "An active assessment period is currently running:\n";
                    $summary .= "  - Started: " . $assessment->started_at->format('Y-m-d H:i') . "\n";
                    $summary .= "  - Due Date: " . $assessment->due_at->format('Y-m-d H:i') . "\n";
                    
                    // Calculate days remaining
                    $daysRemaining = now()->diffInDays($assessment->due_at, false);
                    if ($daysRemaining > 0) {
                        $summary .= "  - Days Remaining: {$daysRemaining} days\n";
                    } elseif ($daysRemaining === 0) {
                        $summary .= "  - Due: TODAY!\n";
                    } else {
                        $summary .= "  - Status: OVERDUE by " . abs($daysRemaining) . " days\n";
                    }
                    
                    // Add threshold method info
                    if ($assessment->threshold_method) {
                        $summary .= "  - Threshold Method: " . strtoupper($assessment->threshold_method) . "\n";
                    }
                    
                    $summary .= "\n**Important Notes:**\n";
                    $summary .= "  - Employees can submit evaluations during this period\n";
                    $summary .= "  - Configuration changes are LOCKED (employees, competencies, CEO ranks, departments cannot be modified)\n";
                    $summary .= "  - Only the due date can be extended if needed\n";
                    $summary .= "  - Admins can view progress but cannot close until all requirements are met\n";
                    
                } else {
                    $summary .= "**Status:** Assessment detected but details unavailable\n";
                }
                
            } else {
                $summary .= "**Status:** NO ACTIVE ASSESSMENT\n\n";
                $summary .= "  - No assessment is currently running\n";
                $summary .= "  - Admins can freely modify configuration (employees, competencies, departments, etc.)\n";
                $summary .= "  - A new assessment can be started at any time from the Admin Home page\n";
            }
            
            $summary .= "\n---\n";
            
            return $summary;
            
        } catch (\Exception $e) {
            Log::error('Failed to load assessment status for help chat', [
                'error' => $e->getMessage()
            ]);
            return "=== ASSESSMENT STATUS ===\nStatus information could not be loaded.\n\n";
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
        
        // ✅ Add organization configuration context
        if ($orgId) {
            $prompt .= $this->getOrgConfigSummary($orgId);
            $prompt .= "\n";
        }

        // ✅ Add assessment status context
        $prompt .= $this->getAssessmentStatus();
        $prompt .= "\n";

        $prompt .= $this->getPaymentStatus();
        $prompt .= "\n";
        
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
        $prompt .= "- If you are asked to give information about another page, ALWAYS load the help document of that page first using the load_help_documents function - do not answer just based on the global index\n";
        $prompt .= "- Tailor your responses based on the organization's enabled features and settings\n";
        $prompt .= "- If a feature is disabled in the organization, explain that it's not available for them\n";
        $prompt .= "- Be helpful, friendly, and concise, but do not answer questions out of the scope of the app\n";
        $prompt .= "- Do NOT answer any sexually oriented questions, propaganda, assaults, bullying, cheating, drugs, weapons, erotic content, or cursing\n";
        $prompt .= "- Provide practical step-by-step guidance when appropriate\n";
        $prompt .= "- If information is in a different section, guide users on how to navigate there\n";
        $prompt .= "- Keep responses clear and easy to understand - always keep in mind the user's role and only talk about topics related to them\n";
        $prompt .= "- Respond in " . ($locale === 'hu' ? 'Hungarian' : ($locale === 'en' ? 'English' : ($locale === 'de' ? 'German' : 'Romanian'))) . "\n";
        $prompt .= "- Do not use technical page names or button identifiers (like 'admin.results') - always use the translated, human-readable names\n";
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

    /**
 * Get payment status information for AI context
 * 
 * @return string
 */
private function getPaymentStatus(): string
{
    try {
        $orgId = session('org_id');
        
        if (!$orgId) {
            return "=== PAYMENT STATUS ===\nPayment information not available (no organization context).\n\n";
        }
        
        // Get open payments
        $openPayments = DB::table('payments')
            ->where('organization_id', $orgId)
            ->whereIn('status', ['initial', 'pending'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $summary = "=== PAYMENT STATUS ===\n\n";
        
        if ($openPayments->isEmpty()) {
            $summary .= "**Status:** ALL PAYMENTS SETTLED ✅\n\n";
            $summary .= "  - No outstanding payments\n";
            $summary .= "  - Organization has full system access\n";
            $summary .= "  - Can start and close assessments\n";
        } else {
            $summary .= "**Status:** {$openPayments->count()} OPEN PAYMENT(S) ⚠️\n\n";
            
            $totalAmount = 0;
            $hasInitial = false;
            $hasAssessment = false;
            
            foreach ($openPayments as $index => $payment) {
                $num = $index + 1;
                $totalAmount += $payment->amount_huf;
                
                // Determine payment type
                $isInitial = ($payment->status === 'initial' || $payment->assessment_id === null);
                
                if ($isInitial) {
                    $hasInitial = true;
                    $summary .= "{$num}. **Initial Setup Payment**\n";
                    $summary .= "   - Amount: " . number_format($payment->amount_huf, 0, ',', ' ') . " HUF\n";
                    $summary .= "   - Status: INITIAL (first login payment required)\n";
                    $summary .= "   - Created: " . \Carbon\Carbon::parse($payment->created_at)->format('Y-m-d') . "\n";
                    $summary .= "   - **Impact:** System access may be limited until paid\n\n";
                } else {
                    $hasAssessment = true;
                    $summary .= "{$num}. **Assessment Payment**\n";
                    $summary .= "   - Amount: " . number_format($payment->amount_huf, 0, ',', ' ') . " HUF\n";
                    $summary .= "   - Status: PENDING\n";
                    $summary .= "   - Related to: Assessment #{$payment->assessment_id}\n";
                    $summary .= "   - Created: " . \Carbon\Carbon::parse($payment->created_at)->format('Y-m-d') . "\n";
                    $summary .= "   - **Impact:** Assessment cannot start until paid\n\n";
                }
            }
            
            $summary .= "**Total Outstanding:** " . number_format($totalAmount, 0, ',', ' ') . " HUF\n\n";
            
            $summary .= "**Important Notes:**\n";
            if ($hasInitial) {
                $summary .= "  - Initial payment must be completed to unlock full system features\n";
            }
            if ($hasAssessment) {
                $summary .= "  - Assessment payments are required to be paid before closing assessment periods.\n";
            }
            $summary .= "  - Payments can be made from the Admin → Payments page\n";
        }
        
        $summary .= "\n---\n";
        
        return $summary;
        
    } catch (\Exception $e) {
        Log::error('Failed to load payment status for help chat', [
            'error' => $e->getMessage()
        ]);
        return "=== PAYMENT STATUS ===\nPayment information could not be loaded.\n\n";
    }
}
}