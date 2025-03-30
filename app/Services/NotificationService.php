<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send a notification to a specific user
     *
     * @param User|int $user The user or user ID
     * @param string $type Notification type (academic, billing, system, message, etc.)
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $actionText Action button text
     * @param string|null $actionUrl Action button URL
     * @param array $metadata Additional metadata
     * @return Notification
     */
    public function sendToUser($user, string $type, string $title, string $message, ?string $actionText = null, ?string $actionUrl = null, array $metadata = [])
    {
        try {
            $userId = $user instanceof User ? $user->id : $user;

            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'action_text' => $actionText,
                'action_url' => $actionUrl,
                'metadata' => $metadata,
            ]);

            // You could trigger real-time notifications here, e.g., using Pusher
            // or implement email/SMS notifications based on user preferences

            return $notification;
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'user_id' => $userId ?? null,
                'type' => $type,
                'title' => $title,
            ]);
            throw $e;
        }
    }

    /**
     * Send a notification to multiple users
     *
     * @param array $userIds Array of user IDs
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $actionText Action button text
     * @param string|null $actionUrl Action button URL
     * @param array $metadata Additional metadata
     * @return array Array of created notifications
     */
    public function sendToMultipleUsers(array $userIds, string $type, string $title, string $message, ?string $actionText = null, ?string $actionUrl = null, array $metadata = [])
    {
        $notifications = [];

        foreach ($userIds as $userId) {
            try {
                $notification = $this->sendToUser($userId, $type, $title, $message, $actionText, $actionUrl, $metadata);
                $notifications[] = $notification;
            } catch (\Exception $e) {
                // Log error but continue with other users
                Log::error('Failed to send notification to user', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                ]);
            }
        }

        return $notifications;
    }

    /**
     * Send a notification to all users with a specific role
     *
     * @param string $role Role name (e.g., 'parent', 'teacher')
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $actionText Action button text
     * @param string|null $actionUrl Action button URL
     * @param array $metadata Additional metadata
     * @return array Array of created notifications
     */
    public function sendToRole(string $role, string $type, string $title, string $message, ?string $actionText = null, ?string $actionUrl = null, array $metadata = [])
    {
        $userIds = User::where('role', $role)->pluck('id')->toArray();
        return $this->sendToMultipleUsers($userIds, $type, $title, $message, $actionText, $actionUrl, $metadata);
    }

    /**
     * Send a system notification to all users
     *
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string|null $actionText Action button text
     * @param string|null $actionUrl Action button URL
     * @param array $metadata Additional metadata
     * @return array Array of created notifications
     */
    public function sendSystemNotificationToAll(string $title, string $message, ?string $actionText = null, ?string $actionUrl = null, array $metadata = [])
    {
        $userIds = User::pluck('id')->toArray();
        return $this->sendToMultipleUsers($userIds, 'system', $title, $message, $actionText, $actionUrl, $metadata);
    }

    /**
     * Get count of unread notifications for a user
     *
     * @param User|int $user User or user ID
     * @return int Count of unread notifications
     */
    public function getUnreadCount($user)
    {
        $userId = $user instanceof User ? $user->id : $user;
        return Notification::where('user_id', $userId)->whereNull('read_at')->count();
    }

    /**
     * Mark all notifications as read for a user
     *
     * @param User|int $user User or user ID
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead($user)
    {
        $userId = $user instanceof User ? $user->id : $user;
        return Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}