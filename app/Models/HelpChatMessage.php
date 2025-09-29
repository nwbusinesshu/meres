<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'role',
        'content'
    ];

    /**
     * Get the session this message belongs to
     */
    public function session()
    {
        return $this->belongsTo(HelpChatSession::class, 'session_id');
    }

    /**
     * Check if message is from user
     */
    public function isUser()
    {
        return $this->role === 'user';
    }

    /**
     * Check if message is from assistant
     */
    public function isAssistant()
    {
        return $this->role === 'assistant';
    }
}