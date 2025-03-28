<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ParentProfile;

new class extends Component {
    public $user;
    public $parentProfile;
    
    // Notification preferences 
    public $notificationPreferences = [];
    
    // Status message for notifications
    public $statusMessage = '';
    public $showNotification = false;

    public function mount()
    {
        $this->user = Auth::user();
        $this->parentProfile = $this->user->parentProfile;

        // Load notification preferences
        $this->loadNotificationPreferences();
    }

    public function loadNotificationPreferences()
    {
        if ($this->parentProfile) {
            // Get the notification preferences from the model or use defaults
            $this->notificationPreferences = $this->parentProfile->notification_preferences ?: 
                ParentProfile::getDefaultNotificationPreferences();
                
            Log::info('Notification preferences loaded', [
                'user_id' => $this->user->id,
                'preferences' => $this->notificationPreferences
            ]);
        } else {
            Log::warning('No parent profile found for notification preferences', [
                'user_id' => $this->user->id
            ]);
            
            // Use default preferences
            $this->notificationPreferences = ParentProfile::getDefaultNotificationPreferences();
        }
    }
    
    public function updateNotificationPreference($channel, $type, $value)
    {
        // Update the local state
        $this->notificationPreferences[$channel][$type] = $value;
        
        Log::info('Notification preference updated', [
            'user_id' => $this->user->id,
            'channel' => $channel,
            'type' => $type,
            'value' => $value
        ]);
    }
    
    public function saveNotificationPreferences()
    {
        try {
            Log::info('Attempting to save notification preferences', [
                'user_id' => $this->user->id,
                'preferences' => $this->notificationPreferences
            ]);
            
            if ($this->parentProfile) {
                $this->parentProfile->updateNotificationPreferences($this->notificationPreferences);
                
                $this->showNotification = true;
                $this->statusMessage = 'Notification preferences updated successfully';
                
                Log::info('Notification preferences saved successfully', [
                    'user_id' => $this->user->id
                ]);
            } else {
                // Create parent profile if it doesn't exist
                $this->parentProfile = ParentProfile::create([
                    'user_id' => $this->user->id,
                    'notification_preferences' => $this->notificationPreferences,
                    'has_completed_profile' => false
                ]);
                
                $this->showNotification = true;
                $this->statusMessage = 'Notification preferences saved successfully';
                
                Log::info('Created new parent profile with notification preferences', [
                    'user_id' => $this->user->id,
                    'profile_id' => $this->parentProfile->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error saving notification preferences', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->showNotification = true;
            $this->statusMessage = 'Error updating notification preferences: ' . $e->getMessage();
        }
    }
    
    public function hideNotification()
    {
        $this->showNotification = false;
    }
}; ?>

<div
    x-data="{
        showNotification: @entangle('showNotification'),
        notificationMessage: @entangle('statusMessage')
    }"
>
    <!-- Notification Toast -->
    <div
        x-show="showNotification"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform scale-90"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        class="fixed z-50 max-w-sm shadow-lg top-4 right-4 alert alert-success"
    >
        <div class="flex justify-between w-full">
            <div class="flex">
                <x-icon name="o-check-circle" class="w-6 h-6" />
                <span x-text="notificationMessage">Preferences updated!</span>
            </div>
            <button @click="$wire.hideNotification()" class="btn btn-sm btn-ghost">×</button>
        </div>
    </div>

    <div class="min-h-screen p-6 bg-base-200">
        <div class="mx-auto max-w-4xl">
            <!-- Back button -->
            <div class="mb-4">
                <a href="{{ route('parents.profile') }}" class="inline-flex items-center gap-2 text-sm font-medium btn btn-ghost hover:bg-base-300">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    Back to Profile
                </a>
            </div>

            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Notification Preferences</h1>
                <p class="mt-1 text-base-content/70">Manage how you receive notifications from our platform</p>
            </div>

            <div class="shadow-xl card bg-base-100">
                <div class="card-body">
                    <h3 class="mb-4 text-lg card-title">Notification Settings</h3>

                    <div class="space-y-6">
                        <!-- Email Notifications -->
                        <div class="pb-4 border-b">
                            <h4 class="mb-2 font-semibold">Email Notifications</h4>

                            <div class="mt-3 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Session Reminders</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.email.session_reminders" 
                                        wire:change="updateNotificationPreference('email', 'session_reminders', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Homework Updates</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.email.homework_updates" 
                                        wire:change="updateNotificationPreference('email', 'homework_updates', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Assessment Results</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.email.assessment_results" 
                                        wire:change="updateNotificationPreference('email', 'assessment_results', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Teacher Messages</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.email.teacher_messages" 
                                        wire:change="updateNotificationPreference('email', 'teacher_messages', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Administrative Updates</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.email.administrative_updates" 
                                        wire:change="updateNotificationPreference('email', 'administrative_updates', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- SMS Notifications -->
                        <div class="pb-4 border-b">
                            <h4 class="mb-2 font-semibold">SMS Notifications</h4>

                            <div class="mt-3 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Session Reminders</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.sms.session_reminders" 
                                        wire:change="updateNotificationPreference('sms', 'session_reminders', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Important Alerts</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.sms.important_alerts" 
                                        wire:change="updateNotificationPreference('sms', 'important_alerts', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>
                            </div>
                        </div>

                        <!-- App Notifications -->
                        <div>
                            <h4 class="mb-2 font-semibold">App Notifications</h4>

                            <div class="mt-3 space-y-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>Push Notifications</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.app.push_notifications" 
                                        wire:change="updateNotificationPreference('app', 'push_notifications', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>

                                <label class="flex items-center justify-between cursor-pointer">
                                    <span>In-App Notifications</span>
                                    <input 
                                        type="checkbox" 
                                        wire:model.live="notificationPreferences.app.in_app_notifications" 
                                        wire:change="updateNotificationPreference('app', 'in_app_notifications', $event.target.checked)"
                                        class="toggle toggle-primary" 
                                    />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between mt-4 card-actions">
                        <a href="{{ route('parents.profile') }}" class="btn btn-ghost">
                            <x-icon name="o-arrow-left" class="w-4 h-4 mr-2" />
                            Back to Profile
                        </a>
                        <button 
                            wire:click="saveNotificationPreferences" 
                            class="btn btn-primary"
                        >
                            Save Preferences
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>