<?php

use Livewire\Volt\Component;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

new class extends Component {
    public User $parent;
    public $activeTab = 'profile';

    // Define available tabs
    public $tabs = [
        'profile' => 'Profile',
        'children' => 'Children',
        'activity' => 'Activity Log',
    ];

    public function mount(User $parent) {
        $this->parent = $parent->load(['parentProfile.children' => function($query) {
            $query->with(['teacher', 'subjects', 'assessments', 'learningSessions']);
        }]);
    }

    public function setActiveTab($tab) {
        $this->activeTab = $tab;
    }

    public function getParentDataProperty() {
        return [
            'Registration Date' => $this->parent->created_at->format('F j, Y'),
            'Last Login' => $this->parent->last_login_at ?? 'Never',
            'Account Status' => $this->parent->hasCompletedProfile() ? 'Active' : 'Incomplete',
            'Total Children' => $this->parent->parentProfile ? $this->parent->parentProfile->children->count() : 0,
        ];
    }

    public function getContactDataProperty() {
        return [
            'Email' => $this->parent->email,
            'Phone' => $this->parent->parentProfile->phone_number ?? 'Not provided',
            'Address' => $this->parent->parentProfile->address ?? 'Not provided',
            'City' => $this->parent->parentProfile->city ?? 'Not provided',
            'State' => $this->parent->parentProfile->state ?? 'Not provided',
            'Postal Code' => $this->parent->parentProfile->postal_code ?? 'Not provided',
            'Country' => $this->parent->parentProfile->country ?? 'Not provided',
        ];
    }

    public function getPreferencesDataProperty() {
        return [
            'Newsletter' => $this->parent->parentProfile && $this->parent->parentProfile->newsletter_subscription ? 'Subscribed' : 'Not subscribed',
            'Communication Method' => $this->parent->parentProfile ? ucfirst($this->parent->parentProfile->preferred_communication_method ?? 'Not specified') : 'Not specified',
        ];
    }

    public function getActivityLogsProperty() {
        // Placeholder for activity logs - you can integrate with a real activity logger
        return [
            ['date' => now()->subDays(1), 'action' => 'Updated profile information', 'ip' => '192.168.1.1'],
            ['date' => now()->subDays(3), 'action' => 'Added a new child', 'ip' => '192.168.1.1'],
            ['date' => now()->subDays(5), 'action' => 'Logged in', 'ip' => '192.168.1.1'],
            ['date' => now()->subDays(10), 'action' => 'Account created', 'ip' => '192.168.1.1'],
        ];
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="h-16 w-16 rounded-full overflow-hidden bg-gray-100 mr-4">
                        <img 
                            src="{{ $parent->parentProfile->profile_photo_path ?? 'https://ui-avatars.com/api/?name=' . urlencode($parent->name) . '&color=7F9CF5&background=EBF4FF' }}" 
                            alt="{{ $parent->name }}" 
                            class="h-full w-full object-cover"
                        >
                    </div>
                    <div>
                        <h3 class="text-2xl leading-6 font-bold text-gray-900">
                            {{ $parent->name }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm text-gray-500">
                            {{ $parent->email }}
                        </p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.parents.edit', $parent->id) }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                        </svg>
                        Edit
                    </a>
                    <a href="{{ route('admin.parents.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                        </svg>
                        Back
                    </a>
                </div>
            </div>
            <div class="border-t border-gray-200">
                <dl>
                    <div class="bg-gray-50 px-4 py-5 sm:grid sm:grid-cols-4 sm:gap-4 sm:px-6">
                        @foreach($this->parentData as $label => $value)
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">{{ $label }}</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($label == 'Account Status')
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $value == 'Active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $value }}
                                    </span>
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                        @endforeach
                    </div>
                </dl>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                @foreach($tabs as $key => $label)
                <button 
                    wire:click="setActiveTab('{{ $key }}')" 
                    class="{{ $activeTab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm"
                >
                    {{ $label }}
                </button>
                @endforeach
            </nav>
        </div>

        <!-- Tab Content -->
        <div>
            <!-- Profile Tab -->
            @if($activeTab === 'profile')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Contact Information Card -->
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-blue-50 to-indigo-50">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <svg class="h-5 w-5 text-blue-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                            </svg>
                            Contact Information
                        </h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <dl>
                            @foreach($this->contactData as $label => $value)
                            <div class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium text-gray-500">{{ $label }}</dt>
                                <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">{{ $value }}</dd>
                            </div>
                            @endforeach
                        </dl>
                    </div>
                </div>

                <!-- Preferences & Additional Information Card -->
                <div class="space-y-6">
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-purple-50 to-pink-50">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                                <svg class="h-5 w-5 text-purple-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Preferences
                            </h3>
                        </div>
                        <div class="border-t border-gray-200">
                            <dl>
                                @foreach($this->preferencesData as $label => $value)
                                <div class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                    <dt class="text-sm font-medium text-gray-500">{{ $label }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        @if($label == 'Newsletter')
                                            <span class="{{ $value == 'Subscribed' ? 'text-green-600' : 'text-gray-500' }}">
                                                {{ $value }}
                                            </span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </dd>
                                </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>

                    @if($parent->parentProfile && $parent->parentProfile->additional_information)
                    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                        <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-amber-50 to-yellow-50">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                                <svg class="h-5 w-5 text-amber-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                                Additional Information
                            </h3>
                        </div>
                        <div class="border-t border-gray-200 p-4">
                            <p class="text-sm text-gray-900">{{ $parent->parentProfile->additional_information }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Children Tab -->
            @if($activeTab === 'children')
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6 bg-gradient-to-r from-green-50 to-teal-50">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 flex items-center">
                            <svg class="h-5 w-5 text-green-500 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                            Children
                        </h3>
                        <a href="#" class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="-ml-0.5 mr-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Child
                        </a>
                    </div>
                </div>
                
                @if($parent->parentProfile && $parent->parentProfile->children->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age/Gender</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School/Grade</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjects</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sessions</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($parent->parentProfile->children as $child)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-500 font-bold">
                                                {{ substr($child->name, 0, 1) }}
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $child->name }}</div>
                                            <div class="text-xs text-gray-500">ID: {{ $child->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $child->age ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ $child->gender ?? 'Not specified' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $child->school_name ?? 'Not specified' }}</div>
                                    <div class="text-xs text-gray-500">{{ $child->grade ? 'Grade ' . $child->grade : 'Not specified' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($child->teacher)
                                        <div class="text-sm text-gray-900">{{ $child->teacher->name }}</div>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Not assigned
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse($child->subjects as $subject)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $subject->name }}
                                            </span>
                                        @empty
                                            <span class="text-xs text-gray-500">None</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $child->learningSessions->count() > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $child->learningSessions->count() }} sessions
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-2">
                                        <a href="#" class="text-blue-600 hover:text-blue-900">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </a>
                                        <a href="#" class="text-indigo-600 hover:text-indigo-900">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                            </svg>
                                        </a>
                                        <button class="text-red-600 hover:text-red-900">
                                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No children</h3>
                    <p class="mt-1 text-sm text-gray-500">Get</p>
                    