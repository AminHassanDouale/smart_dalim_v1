<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'admin_id',
        'message',
        'message_type', // 'user', 'admin', 'system'
        'is_internal', // boolean - for admin-only notes
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'is_internal' => 'boolean',
    ];

    // Relationships
    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function attachments()
    {
        return $this->hasMany(SupportAttachment::class);
    }

    // Scopes
    public function scopeFromUser($query)
    {
        return $query->where('message_type', 'user');
    }

    public function scopeFromAdmin($query)
    {
        return $query->where('message_type', 'admin');
    }

    public function scopeSystem($query)
    {
        return $query->where('message_type', 'system');
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    // Methods
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isFromUser()
    {
        return $this->message_type === 'user';
    }

    public function isFromAdmin()
    {
        return $this->message_type === 'admin';
    }

    public function isSystemMessage()
    {
        return $this->message_type === 'system';
    }
}
