<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'read_at',
        'seen_at',
        'action_text',
        'action_url',
        'metadata',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'seen_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that the notification belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope a query to only include notifications of a given type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark the notification as read.
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark the notification as unread.
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->update(['read_at' => null]);
        }
    }

    /**
     * Mark the notification as seen.
     */
    public function markAsSeen()
    {
        if (is_null($this->seen_at)) {
            $this->update(['seen_at' => now()]);
        }
    }

    /**
     * Check if the notification is read.
     */
    public function isRead()
    {
        return $this->read_at !== null;
    }

    /**
     * Check if the notification is unread.
     */
    public function isUnread()
    {
        return $this->read_at === null;
    }

    /**
     * Create a new academic notification
     */
    public static function createAcademic($userId, $title, $message, $actionText = null, $actionUrl = null, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'academic',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a new billing notification
     */
    public static function createBilling($userId, $title, $message, $actionText = null, $actionUrl = null, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'billing',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a new system notification
     */
    public static function createSystem($userId, $title, $message, $actionText = null, $actionUrl = null, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'system',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Create a new message notification
     */
    public static function createMessage($userId, $title, $message, $actionText = null, $actionUrl = null, $metadata = [])
    {
        return self::create([
            'user_id' => $userId,
            'type' => 'message',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ]);
    }
}
