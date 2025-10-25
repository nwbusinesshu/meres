<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';
    
    protected $fillable = [
        'user_id',
        'organization_id',
        'title',
        'priority',
        'status',
        'closed_at',
        'closed_by',
    ];
    
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'id');
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by', 'id');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id', 'id')
            ->orderBy('created_at', 'asc');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    // Helper methods
    public function isOpen()
    {
        return in_array($this->status, ['open', 'in_progress']);
    }

    public function isClosed()
    {
        return $this->status === 'closed';
    }

    public function getLastMessage()
    {
        return $this->messages()->latest()->first();
    }
}