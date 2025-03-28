<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ParentProfile extends Model
{
    use HasFactory;  // This is crucial for factory functionality

    protected $fillable = [
        'user_id',
        'phone_number',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'number_of_children',
        'additional_information',
        'profile_photo_path',
        'emergency_contacts',
        'has_completed_profile',
        'preferred_communication_method',
        'preferred_session_times',
        'areas_of_interest',
        'newsletter_subscription',
        'notification_preferences',
        'privacy_settings',
    ];

    protected $casts = [
        'emergency_contacts' => 'array',
        'has_completed_profile' => 'boolean',
        'number_of_children' => 'integer',
        'preferred_session_times' => 'array',
        'areas_of_interest' => 'array',
        'newsletter_subscription' => 'boolean',
        'notification_preferences' => 'array',
        'privacy_settings' => 'array',

    ];

    /**
     * Default notification preferences structure
     */
    public static function getDefaultNotificationPreferences(): array
    {
        return [
            'email' => [
                'session_reminders' => true,
                'homework_updates' => true,
                'assessment_results' => true,
                'teacher_messages' => true,
                'administrative_updates' => true,
            ],
            'sms' => [
                'session_reminders' => false,
                'important_alerts' => true,
            ],
            'app' => [
                'push_notifications' => true,
                'in_app_notifications' => true,
            ],
        ];
    }

    /**
     * Default privacy settings
     */
    public static function getDefaultPrivacySettings(): array
    {
        return [
            'share_progress_with_teachers' => true,
            'anonymous_analytics' => true,
            'show_contact_to_teachers' => true,
        ];
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacySettings(array $settings): void
    {
        $this->update(['privacy_settings' => $settings]);
    }

    /**
     * Get the user that owns the parent profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the children for the parent profile.
     */
    public function children()
    {
        return $this->hasMany(Children::class);
    }

    /**
     * Get a notification preference value
     * 
     * @param string $channel The notification channel (email, sms, app)
     * @param string $type The notification type
     * @return bool Whether the notification is enabled
     */
    public function getNotificationPreference(string $channel, string $type): bool
    {
        $preferences = $this->notification_preferences ?: self::getDefaultNotificationPreferences();
        
        return $preferences[$channel][$type] ?? false;
    }

    /**
     * Set a notification preference value
     * 
     * @param string $channel The notification channel (email, sms, app)
     * @param string $type The notification type
     * @param bool $value Whether the notification should be enabled
     * @return void
     */
    public function setNotificationPreference(string $channel, string $type, bool $value): void
    {
        $preferences = $this->notification_preferences ?: self::getDefaultNotificationPreferences();
        
        if (isset($preferences[$channel][$type])) {
            $preferences[$channel][$type] = $value;
            $this->notification_preferences = $preferences;
            $this->save();
        }
    }

    /**
     * Update all notification preferences at once
     * 
     * @param array $preferences The new notification preferences
     * @return void
     */
    public function updateNotificationPreferences(array $preferences): void
    {
        $this->notification_preferences = $preferences;
        $this->save();
    }



}