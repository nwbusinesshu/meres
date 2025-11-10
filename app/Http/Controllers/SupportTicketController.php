<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserType; 

class SupportTicketController extends Controller
{
    /**
     * Get user's tickets (for help modal)
     */
    public function getUserTickets(Request $request)
    {
        $userId = session('uid');
        $organizationId = session('org_id');
        
        if (!$userId || !$organizationId) {
            return response()->json([
                'success' => false,
                'error' => __('support.user_not_authenticated')
            ], 401);
        }

        try {
            $tickets = SupportTicket::forUser($userId)
                ->forOrganization($organizationId)
                ->with(['messages' => function($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($ticket) {
                    $lastMessage = $ticket->messages->first();
                    return [
                        'id' => $ticket->id,
                        'title' => $ticket->title,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'created_at' => $ticket->created_at->toIso8601String(),
                        'last_message' => $lastMessage ? $lastMessage->message : null,
                        'last_message_at' => $lastMessage ? $lastMessage->created_at->toIso8601String() : null,
                        'message_count' => $ticket->messages()->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'tickets' => $tickets
            ]);

        } catch (\Exception $e) {
            Log::error('Support ticket list error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => __('support.failed_to_load_tickets')
            ], 500);
        }
    }

    /**
     * Load a specific ticket with all messages
     */
    public function loadTicket(Request $request, $ticketId)
    {
        $userId = session('uid');
        $organizationId = session('org_id');
        
        if (!$userId || !$organizationId) {
            return response()->json([
                'success' => false,
                'error' => __('support.user_not_authenticated')
            ], 401);
        }

        try {
            $ticket = SupportTicket::where('id', $ticketId)
                ->where('user_id', $userId)
                ->where('organization_id', $organizationId)
                ->with(['messages' => function($query) {
                    $query->orderBy('created_at', 'asc');
                }, 'messages.user'])
                ->firstOrFail();

            $messages = $ticket->messages->map(function($msg) {
                return [
                    'user_name' => $msg->user->name,
                    'message' => $msg->message,
                    'is_staff_reply' => $msg->is_staff_reply,
                    'created_at' => $msg->created_at->toIso8601String()
                ];
            });

            return response()->json([
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at->toIso8601String(),
                    'messages' => $messages
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Support ticket load error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => __('support.failed_to_load_ticket')
            ], 500);
        }
    }

    /**
     * Create a new ticket
     */
    public function createTicket(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent'
        ]);

        $userId = session('uid');
        $organizationId = session('org_id');
        
        if (!$userId || !$organizationId) {
            return response()->json([
                'success' => false,
                'error' => __('support.user_not_authenticated')
            ], 401);
        }

        try {
            DB::beginTransaction();

            $ticket = SupportTicket::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'title' => $validated['title'],
                'priority' => $validated['priority'],
                'status' => 'open',
            ]);

            SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => $validated['message'],
                'is_staff_reply' => false,
            ]);

            DB::commit();

            try {
                $org = \App\Models\Organization::find($organizationId);
                $superadmins = User::where('type', UserType::SUPERADMIN)->whereNull('removed_at')->get();
                $messages = \App\Models\SupportTicketMessage::where('ticket_id', $ticket->id)->with('user')->orderBy('created_at')->get();
                
                foreach ($superadmins as $admin) {
                    \Mail::to($admin->email)->send(new \App\Mail\TicketNotificationMail(
                        $org, $admin, $ticket, $messages, config('app.url') . '/login', $admin->locale ?? 'hu', true
                    ));
                }
                \Log::info('ticket.emails.created', ['ticket_id' => $ticket->id, 'count' => $superadmins->count()]);
            } catch (\Throwable $e) {
                \Log::error('ticket.emails.created.failed', ['error' => $e->getMessage()]);
            }

            Log::info('Support ticket created', [
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'organization_id' => $organizationId
            ]);

            return response()->json([
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Support ticket creation error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => __('support.failed_to_create_ticket')
            ], 500);
        }
    }

    /**
     * Add a reply to a ticket (user side)
     */
    public function replyToTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'message' => 'required|string'
        ]);

        $userId = session('uid');
        $organizationId = session('org_id');
        
        if (!$userId || !$organizationId) {
            return response()->json([
                'success' => false,
                'error' => __('support.user_not_authenticated')
            ], 401);
        }

        try {
            $ticket = SupportTicket::where('id', $ticketId)
                ->where('user_id', $userId)
                ->where('organization_id', $organizationId)
                ->firstOrFail();

            // Don't allow replies to closed tickets
            if ($ticket->isClosed()) {
                return response()->json([
                    'success' => false,
                    'error' => __('support.cannot_reply_to_closed')
                ], 403);
            }

            $message = SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => $validated['message'],
                'is_staff_reply' => false,
            ]);

            try {
                $org = \App\Models\Organization::find($organizationId);
                $superadmins = User::where('type', UserType::SUPERADMIN)->whereNull('removed_at')->get();
                $messages = \App\Models\SupportTicketMessage::where('ticket_id', $ticket->id)->with('user')->orderBy('created_at')->get();
                
                foreach ($superadmins as $admin) {
                    \Mail::to($admin->email)->send(new \App\Mail\TicketNotificationMail(
                        $org, $admin, $ticket, $messages, config('app.url') . '/login', $admin->locale ?? 'hu', false
                    ));
                }
                \Log::info('ticket.emails.updated', ['ticket_id' => $ticket->id, 'message_id' => $message->id]);
            } catch (\Throwable $e) {
                \Log::error('ticket.emails.updated.failed', ['error' => $e->getMessage()]);
            }

            // Update ticket status if needed
            if ($ticket->status === 'closed') {
                $ticket->status = 'open';
                $ticket->save();
            }

            Log::info('Support ticket reply added', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'user_name' => $message->user->name,
                    'message' => $message->message,
                    'is_staff_reply' => false,
                    'created_at' => $message->created_at->toIso8601String()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Support ticket reply error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => __('support.failed_to_add_reply')
            ], 500);
        }
    }
}