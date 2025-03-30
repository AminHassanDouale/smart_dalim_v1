<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'title',
        'description',
        'category',
        'priority',
        'status',
        'related_entity_type',
        'related_entity_id',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'closed_by_user',
        'reopened_at',
        'satisfaction_rating',
        'satisfaction_comment',
        'rated_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'rated_at' => 'datetime',
        'closed_by_user' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(SupportAttachment::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['open', 'in_progress']);
    }

    // Methods
    public function markAsInProgress()
    {
        $this->update([
            'status' => 'in_progress',
            'first_response_at' => $this->first_response_at ?? now(),
        ]);
    }

    public function markAsResolved()
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function markAsClosed($byUser = false)
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user' => $byUser,
        ]);
    }

    public function reopen()
    {
        $this->update([
            'status' => 'open',
            'reopened_at' => now(),
        ]);
    }

    public function isActive()
    {
        return in_array($this->status, ['open', 'in_progress']);
    }

    public function getLastMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }
}
