<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SuperadminTicketController extends Controller
{
    /**
     * Show superadmin tickets page
     */
    public function index()
    {
        return view('superadmin.tickets');
    }

    /**
     * Get all tickets for superadmin (with filters)
     */
    public function getAllTickets(Request $request)
    {
        try {
            $query = SupportTicket::with(['user', 'organization', 'messages' => function($q) {
                $q->orderBy('created_at', 'desc')->limit(1);
            }]);

            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                if ($request->status === 'open') {
                    $query->whereIn('status', ['open', 'in_progress']);
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has('organization_id') && $request->organization_id !== 'all') {
                $query->where('organization_id', $request->organization_id);
            }

            if ($request->has('priority') && $request->priority !== 'all') {
                $query->where('priority', $request->priority);
            }

            $tickets = $query->orderBy('created_at', 'desc')
                ->get()
                ->map(function($ticket) {
                    $lastMessage = $ticket->messages->first();
                    return [
                        'id' => $ticket->id,
                        'title' => $ticket->title,
                        'user_name' => $ticket->user->name,
                        'user_email' => $ticket->user->email,
                        'organization_name' => $ticket->organization->name,
                        'priority' => $ticket->priority,
                        'status' => $ticket->status,
                        'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                        'last_message' => $lastMessage ? $lastMessage->message : null,
                        'last_message_at' => $lastMessage ? $lastMessage->created_at->format('Y-m-d H:i:s') : null,
                        'message_count' => $ticket->messages()->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'tickets' => $tickets
            ]);

        } catch (\Exception $e) {
            Log::error('Superadmin ticket list error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load tickets'
            ], 500);
        }
    }

    /**
     * Get ticket details with all messages
     */
    public function getTicketDetails(Request $request, $ticketId)
    {
        try {
            $ticket = SupportTicket::with([
                'user', 
                'organization', 
                'closedBy',
                'messages' => function($query) {
                    $query->orderBy('created_at', 'asc');
                }, 
                'messages.user'
            ])->findOrFail($ticketId);

            $messages = $ticket->messages->map(function($msg) {
                return [
                    'user_name' => $msg->user->name,
                    'message' => $msg->message,
                    'is_staff_reply' => $msg->is_staff_reply,
                    'created_at' => $msg->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'ticket' => [
                    'id' => $ticket->id,
                    'title' => $ticket->title,
                    'user_name' => $ticket->user->name,
                    'user_email' => $ticket->user->email,
                    'organization_name' => $ticket->organization->name,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'created_at' => $ticket->created_at->format('Y-m-d H:i:s'),
                    'closed_at' => $ticket->closed_at ? $ticket->closed_at->format('Y-m-d H:i:s') : null,
                    'closed_by_name' => $ticket->closedBy ? $ticket->closedBy->name : null,
                    'messages' => $messages
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Superadmin ticket details error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load ticket details'
            ], 500);
        }
    }

    /**
     * Reply to a ticket (superadmin)
     */
    public function replyToTicket(Request $request, $ticketId)
    {
        $validated = $request->validate([
            'message' => 'required|string'
        ]);

        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $ticket = SupportTicket::findOrFail($ticketId);

            $message = SupportTicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => $userId,
                'message' => $validated['message'],
                'is_staff_reply' => true,
            ]);

            // Update ticket status to in_progress if it was open
            if ($ticket->status === 'open') {
                $ticket->status = 'in_progress';
                $ticket->save();
            }

            Log::info('Superadmin replied to ticket', [
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
                'admin_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'user_name' => $message->user->name,
                    'message' => $message->message,
                    'is_staff_reply' => true,
                    'created_at' => $message->created_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Superadmin ticket reply error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to add reply'
            ], 500);
        }
    }

    /**
     * Close a ticket
     */
    public function closeTicket(Request $request, $ticketId)
    {
        $userId = session('uid');
        
        if (!$userId) {
            return response()->json([
                'success' => false,
                'error' => 'User not authenticated'
            ], 401);
        }

        try {
            $ticket = SupportTicket::findOrFail($ticketId);

            $ticket->status = 'closed';
            $ticket->closed_at = now();
            $ticket->closed_by = $userId;
            $ticket->save();

            Log::info('Ticket closed', [
                'ticket_id' => $ticket->id,
                'closed_by' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket closed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Ticket close error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to close ticket'
            ], 500);
        }
    }

    /**
     * Reopen a ticket
     */
    public function reopenTicket(Request $request, $ticketId)
    {
        try {
            $ticket = SupportTicket::findOrFail($ticketId);

            $ticket->status = 'open';
            $ticket->closed_at = null;
            $ticket->closed_by = null;
            $ticket->save();

            Log::info('Ticket reopened', [
                'ticket_id' => $ticket->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket reopened successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Ticket reopen error', [
                'ticket_id' => $ticketId,
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to reopen ticket'
            ], 500);
        }
    }

    /**
     * Get organizations for filter dropdown
     */
    public function getOrganizations()
    {
        try {
            $organizations = Organization::whereNull('removed_at')
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'organizations' => $organizations
            ]);

        } catch (\Exception $e) {
            Log::error('Get organizations error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load organizations'
            ], 500);
        }
    }
}