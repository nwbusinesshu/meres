<?php

namespace App\Http\Controllers;

use App\Models\HelpChatSession;
use App\Services\HelpChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class HelpChatController extends Controller
{
    private HelpChatService $chatService;

    public function __construct(HelpChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Get help content for a specific page
     */
    public function getHelpContent(Request $request)
    {
        $validated = $request->validate([
            'view_key' => 'required|string|max:100',
            'locale' => 'required|string|max:10'
        ]);

        $viewKey = $validated['view_key'];
        $locale = $validated['locale'];

        // Build file path
        $filePath = resource_path("help/{$locale}/{$viewKey}.md");

        // Check if file exists
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'error' => 'not_found',
                'message' => 'Help content not available for this page.'
            ], 404);
        }

        try {
            // Read the file
            $fileContent = file_get_contents($filePath);

            // Parse YAML front matter and markdown content
            $parsed = $this->parseMarkdownWithFrontMatter($fileContent);

            // Convert markdown to HTML with Table extension
            $environment = new Environment([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new TableExtension());
            
            $converter = new MarkdownConverter($environment);
            $htmlContent = $converter->convert($parsed['content'])->getContent();

            return response()->json([
                'success' => true,
                'content' => $htmlContent,
                'metadata' => $parsed['metadata']
            ]);

        } catch (\Exception $e) {
            Log::error('Help content loading error', [
                'view_key' => $viewKey,
                'locale' => $locale,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'processing_error',
                'message' => 'Failed to load help content.'
            ], 500);
        }
    }

    /**
     * Parse markdown file with YAML front matter
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
     * Send a chat message and get AI response
     * UPDATED: Now detects function call requests
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|integer|exists:help_chat_sessions,id',
            'view_key' => 'nullable|string|max:100',
            'locale' => 'nullable|string|max:10',
            'welcome_mode' => 'nullable|boolean',
            'welcome_response' => 'nullable|string|max:2000'
        ]);

        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            // Get or create session
            if (isset($validated['session_id'])) {
                $session = HelpChatSession::where('id', $validated['session_id'])
                    ->where('user_id', $userId)
                    ->firstOrFail();
            } else {
                $session = HelpChatSession::create([
                    'user_id' => $userId,
                    'title' => 'New Conversation',
                    'view_key' => $validated['view_key'] ?? 'unknown',
                    'locale' => $validated['locale'] ?? app()->getLocale(),
                    'last_message_at' => now()
                ]);
            }

            // Handle welcome mode
            if (isset($validated['welcome_mode']) && $validated['welcome_mode'] == 1) {
                $welcomeResponse = $validated['welcome_response'] ?? 'Üdvözlöm! Segíthetek?';
                $session->messages()->create([
                    'role' => 'assistant',
                    'content' => $welcomeResponse
                ]);
                
                $session->title = 'Első belépés';
                $session->last_message_at = now();
                $session->save();
                
                return response()->json([
                    'success' => true,
                    'response' => $welcomeResponse,
                    'timestamp' => now()->toIso8601String(),
                    'model' => 'welcome-message',
                    'session_id' => $session->id,
                    'session_title' => $session->title
                ]);
            }

            // Normal mode: Send message to AI
            $response = $this->chatService->sendMessage(
                $validated['message'],
                $session
            );

            if (!$response) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get AI response. Please try again.'
                ], 500);
            }

            // UPDATED: Check if response is a function call request
            if (isset($response['function_call'])) {
                return response()->json([
                    'success' => true,
                    'function_call' => $response['function_call'],
                    'session_id' => $response['session_id']
                ]);
            }

            // Normal AI response
            return response()->json([
                'success' => true,
                'response' => $response['message'],
                'timestamp' => $response['timestamp'],
                'model' => $response['model'],
                'session_id' => $session->id,
                'session_title' => $session->title
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat controller error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }

    /**
     * NEW: Load additional help documents and get final AI response
     * Called by frontend after receiving function_call request
     */
    public function loadAdditionalDocs(Request $request)
    {
        $validated = $request->validate([
            'session_id' => 'required|integer|exists:help_chat_sessions,id',
            'view_keys' => 'required|array|min:1|max:10',
            'view_keys.*' => 'required|string|max:100',
            'original_message' => 'required|string|max:1000'
        ]);

        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            // Load session
            $session = HelpChatSession::where('id', $validated['session_id'])
                ->where('user_id', $userId)
                ->firstOrFail();

            // Get user role for content filtering
            $userRole = $session->user->type ?? 'guest';

            // Load additional help documents
            $additionalDocs = $this->chatService->loadAdditionalHelpDocuments(
                $validated['view_keys'],
                $session->locale,
                $userRole
            );

            Log::info('Help chat: loaded additional documents', [
                'session_id' => $session->id,
                'view_keys' => $validated['view_keys'],
                'docs_length' => strlen($additionalDocs)
            ]);

            // Send message again with additional context
            $response = $this->chatService->sendMessage(
                $validated['original_message'],
                $session,
                $additionalDocs
            );

            if (!$response) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get AI response after loading documents.'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'response' => $response['message'],
                'timestamp' => $response['timestamp'],
                'model' => $response['model'],
                'session_id' => $session->id,
                'loaded_docs' => $validated['view_keys']
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat load additional docs error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load additional documents.'
            ], 500);
        }
    }

    /**
     * Get list of user's chat sessions
     */
    public function listSessions(Request $request)
    {
        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $sessions = HelpChatSession::forUser($userId)
                ->with(['messages' => function($query) {
                    $query->orderBy('created_at', 'asc');
                }])
                ->limit(50)
                ->get()
                ->map(function($session) {
                    return [
                        'id' => $session->id,
                        'title' => $session->title,
                        'view_key' => $session->view_key,
                        'last_message_at' => $session->last_message_at->toIso8601String(),
                        'message_count' => $session->messages->count(),
                        'preview' => $session->messages->first()->content ?? ''
                    ];
                });

            return response()->json([
                'success' => true,
                'sessions' => $sessions
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat list sessions error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load conversations'
            ], 500);
        }
    }

    /**
     * Load a specific session with all messages
     */
    public function loadSession(Request $request, $sessionId)
    {
        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $session = HelpChatSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->with(['messages' => function($query) {
                    $query->orderBy('created_at', 'asc');
                }])
                ->firstOrFail();

            $messages = $session->messages->map(function($msg) {
                return [
                    'role' => $msg->role,
                    'content' => $msg->content,
                    'timestamp' => $msg->created_at->toIso8601String()
                ];
            });

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'view_key' => $session->view_key,
                    'locale' => $session->locale,
                    'messages' => $messages
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat load session error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load session'
            ], 500);
        }
    }

    /**
     * Create a new chat session
     */
    public function createSession(Request $request)
    {
        $validated = $request->validate([
            'view_key' => 'nullable|string|max:100',
            'locale' => 'nullable|string|max:10'
        ]);

        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $session = HelpChatSession::create([
                'user_id' => $userId,
                'title' => 'New Conversation',
                'view_key' => $validated['view_key'] ?? 'unknown',
                'locale' => $validated['locale'] ?? app()->getLocale(),
                'last_message_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'session' => [
                    'id' => $session->id,
                    'title' => $session->title,
                    'view_key' => $session->view_key,
                    'locale' => $session->locale
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat create session error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create session'
            ], 500);
        }
    }

    /**
     * Delete a chat session
     */
    public function deleteSession(Request $request, $sessionId)
    {
        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $session = HelpChatSession::where('id', $sessionId)
                ->where('user_id', $userId)
                ->firstOrFail();

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Session deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat delete session error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete session'
            ], 500);
        }
    }
}