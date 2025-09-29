<?php

namespace App\Http\Controllers;

use App\Models\HelpChatSession;
use App\Services\HelpChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelpChatController extends Controller
{
    private HelpChatService $chatService;

    public function __construct(HelpChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Send a chat message and get AI response
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'session_id' => 'nullable|integer|exists:help_chat_sessions,id',
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
            // Get or create session
            if (isset($validated['session_id'])) {
                // Load existing session
                $session = HelpChatSession::where('id', $validated['session_id'])
                    ->where('user_id', $userId)
                    ->firstOrFail();
            } else {
                // Create new session
                $session = HelpChatSession::create([
                    'user_id' => $userId,
                    'title' => 'New Conversation',
                    'view_key' => $validated['view_key'] ?? 'unknown',
                    'locale' => $validated['locale'] ?? app()->getLocale(),
                    'last_message_at' => now()
                ]);
            }

            // Send message to AI
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
                ->limit(50) // Last 50 conversations
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
                    'last_message_at' => $session->last_message_at->toIso8601String()
                ],
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat load session error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load conversation'
            ], 404);
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

            $session->delete(); // Cascade deletes messages

            return response()->json([
                'success' => true,
                'message' => 'Conversation deleted'
            ]);

        } catch (\Exception $e) {
            Log::error('Help chat delete session error', [
                'session_id' => $sessionId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete conversation'
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
                'error' => 'Failed to create conversation'
            ], 500);
        }
    }
}