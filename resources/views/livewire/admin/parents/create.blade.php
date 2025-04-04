<?php

use Livewire\Volt\Component;
use App\Models\User;
use App\Models\ParentProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone_number = '';
    public $address = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country = '';
    public $additional_information = '';
    public $newsletter_subscription = false;
    public $preferred_communication_method = 'email';
    
    // Validation rules
    protected function rules() {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'additional_information' => 'nullable|string',
            'newsletter_subscription' => 'boolean',
            'preferred_communication_method' => 'nullable|string|in:email,phone,sms',
        ];
    }
    
    public function createParent() {
        $this->validate();
        
        try {
            DB::beginTransaction();
            
            // Create User
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => User::ROLE_PARENT,
            ]);
            
            // Create ParentProfile
            $parentProfile = ParentProfile::create([
                'user_id' => $user->id,
                'phone_number' => $this->phone_number,
                'address' => $this->address,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'country' => $this->country,
                'additional_information' => $this->additional_information,
                'newsletter_subscription' => $this->newsletter_subscription,
                'preferred_communication_method' => $this->preferred_communication_method,
                'has_completed_profile' => true,
                'notification_preferences' => ParentProfile::getDefaultNotificationPreferences(),
                'privacy_settings' => ParentProfile::getDefaultPrivacySettings(),
            ]);
            
            DB::commit();
            
            session()->flash('message', 'Parent created successfully!');
            return redirect()->route('admin.parents.index');
            
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Error creating parent: ' . $e->getMessage());
        }
    }
};
?>

<div class="max-w-4xl mx-auto py-10 sm:px-6 lg:px-8">
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-900 mb-6">Create New Parent</h1>
            
            @if (session()->has('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif
            
            <form wire:submit="createParent" class="space-y-6">
                <!-- User Information -->
                <div class="bg-gray-50 p-4 rounded-md">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Account Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model="name" id="name" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" wire:model="email" id="email" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" wire:model="password" id="password" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                            <input type="password" wire:model="password_confirmation" id="password_confirmation" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="bg-gray-50 p-4 rounded-md">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Contact Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone_number" class="block text-sm font-medium text-gray-700">Phone Number</label>
                            <input type="text" wire:model="phone_number" id="phone_number" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('phone_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                            <input type="text" wire:model="address" id="address" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" wire:model="city" id="city" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">State/Province</label>
                            <input type="text" wire:model="state" id="state" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code</label>
                            <input type="text" wire:model="postal_code" id="postal_code" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                            <input type="text" wire:model="country" id="country" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                            @error('country') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                
                <!-- Preferences -->
                <div class="bg-gray-50 p-4 rounded-md">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Preferences</h2>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label for="preferred_communication_method" class="block text-sm font-medium text-gray-700">Preferred Communication Method</label>
                            <select wire:model="preferred_communication_method" id="preferred_communication_method" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="sms">SMS</option>
                            </select>
                            @error('preferred_communication_method') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input type="checkbox" wire:model="newsletter_subscription" id="newsletter_subscription" class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="newsletter_subscription" class="font-medium text-gray-700">Subscribe to Newsletter</label>
                                <p class="text-gray-500">Receive updates about new features, promotions, and educational content.</p>
                            </div>
                        </div>
                        
                        <div>
                            <label for="additional_information" class="block text-sm font-medium text-gray-700">Additional Information</label>
                            <textarea wire:model="additional_information" id="additional_information" rows="3" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"></textarea>
                            @error('additional_information') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <a href="{{ route('admin.parents.index') }}" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Create Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>