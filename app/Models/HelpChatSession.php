<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'view_key',
        'locale',
        'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime'
    ];

    /**
     * Get the user that owns the session
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all messages for this session
     */
    public function messages()
    {
        return $this->hasMany(HelpChatMessage::class, 'session_id');
    }

    /**
     * Get messages ordered by creation time
     */
    public function orderedMessages()
    {
        return $this->messages()->orderBy('created_at', 'asc');
    }

    /**
     * Update the last message timestamp
     */
    public function touchLastMessage()
    {
        $this->last_message_at = now();
        $this->save();
    }

    /**
     * Scope: Get recent sessions for a user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)
                     ->orderBy('last_message_at', 'desc');
    }
}