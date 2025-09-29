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
     * @return array|null Response array with message and session info, or null on failure
     */
    public function sendMessage(string $message, HelpChatSession $session): ?array
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
            'role' => $session->user->type ?? 'guest',
            'locale' => $session->locale
        ];

        $systemPrompt = $this->buildSystemPrompt($context);
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
                'max_tokens' => 500,
            ]);

            if (!$response->ok()) {
                Log::error('Help chat API error', [
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
            $this->saveMessageToDatabase($session, 'user', $message);
            $this->saveMessageToDatabase($session, 'assistant', $content);

            // Update session last_message_at
            $session->touchLastMessage();

            // Generate title if this is the first message
            if ($session->messages()->count() === 2) { // 2 messages = first exchange
                $this->generateAndSaveTitle($session, $message);
            }

            Log::info('Help chat successful', [
                'session_id' => $session->id,
                'view_key' => $session->view_key,
                'message_length' => strlen($message),
                'response_length' => strlen($content)
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
     * Build the system prompt with context
     */
    private function buildSystemPrompt(array $context): string
    {
        $viewKey = $context['view_key'] ?? 'unknown';
        $role = $context['role'] ?? 'guest';
        $locale = $context['locale'] ?? 'en';

        $prompt = "You are a helpful assistant for an HR assessment application. ";
        $prompt .= "You help users understand features and navigate the system.\n\n";
        
        $prompt .= "CURRENT CONTEXT:\n";
        $prompt .= "- Page: {$viewKey}\n";
        $prompt .= "- User Role: {$role}\n";
        $prompt .= "- Language: {$locale}\n\n";
        
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "- Be helpful, friendly, and concise\n";
        $prompt .= "- Provide practical guidance about the current page\n";
        $prompt .= "- If you're unsure about specific features, be honest\n";
        $prompt .= "- Keep responses clear and easy to understand\n";
        $prompt .= "- Respond in " . ($locale === 'hu' ? 'Hungarian' : 'English') . "\n";
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