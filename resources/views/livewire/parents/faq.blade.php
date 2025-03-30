<?php

namespace App\Livewire\Parents;

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $user;
    public $searchQuery = '';
    public $categories = [];
    public $activeCategory = 'all';

    // FAQ data organized by categories
    public $faqs = [];
    public function getCategoryIcon($key)
    {
        return match($key) {
            'all' => 'fa-list',
            'account' => 'fa-user',
            'billing' => 'fa-credit-card',
            'sessions' => 'fa-calendar',
            'technical' => 'fa-wrench',
            'reports' => 'fa-chart-line',
            default => 'fa-circle-question'
        };
    }
    public function mount()
    {
        $this->user = Auth::user();

        // Define FAQ categories
        $this->categories = [
            'all' => 'All Questions',
            'account' => 'Account & Profile',
            'billing' => 'Billing & Payments',
            'sessions' => 'Learning Sessions',
            'technical' => 'Technical Support',
            'reports' => 'Progress & Reports'
        ];

        // Load FAQ data with categories
        $this->faqs = [
            [
                'question' => 'How do I reset my password?',
                'answer' => 'You can reset your password by clicking on the "Forgot Password" link on the login page. You will receive an email with instructions to create a new password. Make sure to check your spam folder if you don\'t see the email in your inbox.',
                'category' => 'account'
            ],
            [
                'question' => 'How do I update my child\'s information?',
                'answer' => 'To update your child\'s information, go to the "Children" section from the dashboard, select the child you want to update, and click on the "Edit Profile" button. You can update details such as name, age, grade, school, and subjects of interest.',
                'category' => 'account'
            ],
            [
                'question' => 'How do I schedule a learning session?',
                'answer' => 'To schedule a new learning session, navigate to the "Schedule" section and click on "New Session". Select your child, the subject, preferred date and time, and the teacher if you have a preference. Once submitted, you\'ll receive a confirmation when the session is scheduled.',
                'category' => 'sessions'
            ],
            [
                'question' => 'How do I cancel or reschedule a session?',
                'answer' => 'You can cancel or reschedule a session by going to the "Sessions" page, finding the session you want to modify, and clicking the "Cancel" or "Reschedule" button. Please note that cancellations made less than 24 hours before the scheduled time may incur a fee.',
                'category' => 'sessions'
            ],
            [
                'question' => 'How do I view my child\'s progress?',
                'answer' => 'You can view your child\'s progress by going to the "Progress" section and selecting the child you want to review. The dashboard displays performance metrics, attendance history, subject proficiency, and teacher feedback. You can also generate detailed reports for specific time periods.',
                'category' => 'reports'
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and bank transfers. Payment information can be updated in the "Billing" section of your account settings.',
                'category' => 'billing'
            ],
            [
                'question' => 'How is my child\'s performance evaluated?',
                'answer' => 'Your child\'s performance is evaluated based on multiple factors: session participation, completion of assignments, assessment results, and teacher observations. Each session includes a performance score out of 10, and detailed feedback is provided after each session.',
                'category' => 'reports'
            ],
            [
                'question' => 'I\'m having technical issues during a session. What should I do?',
                'answer' => 'If you encounter technical issues during a session, first try refreshing your browser. Make sure you have a stable internet connection and your camera/microphone permissions are enabled. If problems persist, use the live chat support option or call our technical support team at the number provided in your session confirmation email.',
                'category' => 'technical'
            ],
            [
                'question' => 'How do I download progress reports?',
                'answer' => 'To download progress reports, navigate to the "Reports" section, select the child, choose the time period, and click on "Generate Report". You can download the report in PDF format by clicking the "Download PDF" button at the top of the generated report.',
                'category' => 'reports'
            ],
            [
                'question' => 'What happens if I miss a scheduled payment?',
                'answer' => 'If you miss a scheduled payment, you\'ll receive a notification and a grace period of 3 days to complete the payment. After that, access to scheduling new sessions may be temporarily limited until the payment is made. You can make a manual payment through the "Billing" section at any time.',
                'category' => 'billing'
            ],
            [
                'question' => 'How do I update my billing information?',
                'answer' => 'To update your billing information, go to "Account Settings" and select the "Billing" tab. Click on "Update Payment Method" to add or change your credit card details or other payment information. Your data is securely encrypted and processed according to PCI DSS standards.',
                'category' => 'billing'
            ],
            [
                'question' => 'What browser works best with the learning platform?',
                'answer' => 'Our platform works best with the latest versions of Chrome, Firefox, Safari, and Edge. For the optimal learning experience, we recommend using Google Chrome with a stable internet connection of at least 5 Mbps download and 1 Mbps upload speed.',
                'category' => 'technical'
            ]
        ];
    }

    public function setActiveCategory($category)
    {
        $this->activeCategory = $category;
    }

    public function getFilteredFaqsProperty()
    {
        $faqs = collect($this->faqs);

        // Filter by category if not "all"
        if ($this->activeCategory != 'all') {
            $faqs = $faqs->where('category', $this->activeCategory);
        }

        // Filter by search query if exists
        if (!empty($this->searchQuery)) {
            $searchQuery = strtolower($this->searchQuery);
            $faqs = $faqs->filter(function($faq) use ($searchQuery) {
                return str_contains(strtolower($faq['question']), $searchQuery) ||
                       str_contains(strtolower($faq['answer']), $searchQuery);
            });
        }

        return $faqs->all();
    }
}; ?>

<div class="min-h-screen p-6 bg-base-200">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="mb-8 overflow-hidden text-white shadow-lg rounded-xl bg-gradient-to-r from-primary to-secondary">
            <div class="p-6 md:p-8">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div>
                        <h1 class="text-3xl font-bold">Frequently Asked Questions</h1>
                        <p class="mt-2 text-white/80">Find answers to common questions about our learning platform</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('parents.dashboard') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="w-4 h-4 mr-1 fa-solid fa-house"></i>
                            Dashboard
                        </a>
                        <a href="{{ route('parents.support.index') }}" class="text-white btn btn-ghost btn-sm bg-white/10">
                            <i class="w-4 h-4 mr-1 fa-solid fa-headset"></i>
                            Support Center
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Sidebar with Categories -->
            <div class="lg:col-span-1">
                <div class="shadow-lg card bg-base-100">
                    <div class="card-body">
                        <h2 class="mb-4 text-lg font-semibold">Categories</h2>
                        <ul class="menu bg-base-100">
                            @foreach($categories as $key => $label)
                                <li>
                                    <a
                                        href="#"
                                        wire:click.prevent="setActiveCategory('{{ $key }}')"
                                        class="{{ $activeCategory === $key ? 'active font-medium' : '' }}"
                                    >
                                        <i class="fa-solid {{ $this->getCategoryIcon($key) }}"></i>
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-6">
                            <div class="p-4 text-center bg-primary/10 rounded-box">
                                <h3 class="font-medium">Need more help?</h3>
                                <p class="mt-2 text-sm">Our support team is ready to assist you with any questions.</p>
                                <a href="{{ route('support.create') }}" class="mt-4 btn btn-primary btn-sm btn-block">
                                    <i class="mr-2 fa-solid fa-ticket"></i>
                                    Create Support Ticket
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FAQ Content -->
            <div class="lg:col-span-3">
                <!-- FAQ Search Box -->
                <div class="mb-6">
                    <div class="relative">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="searchQuery"
                            placeholder="Search for answers..."
                            class="w-full pl-10 pr-10 input input-bordered"
                        >
                        <div class="absolute transform -translate-y-1/2 left-3 top-1/2">
                            <i class="fa-solid fa-magnifying-glass text-base-content/60"></i>
                        </div>
                        @if($searchQuery)
                            <button
                                class="absolute transform -translate-y-1/2 right-3 top-1/2 btn btn-ghost btn-circle btn-sm"
                                wire:click="$set('searchQuery', '')"
                            >
                                <i class="fa-solid fa-times"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <!-- FAQ Cards -->
                <div class="shadow-xl card bg-base-100">
                    <div class="card-body">
                        <h2 class="card-title">
                            {{ $categories[$activeCategory] }}
                            @if($searchQuery)
                                <span class="text-sm font-normal">- Search results for "{{ $searchQuery }}"</span>
                            @endif
                        </h2>

                        <div class="my-4 divider"></div>

                        @if(count($this->filteredFaqs) > 0)
                            <div class="space-y-4">
                                @foreach($this->filteredFaqs as $index => $faq)
                                    <div
                                        x-data="{ open: false }"
                                        class="border rounded-lg shadow-sm border-base-300"
                                    >
                                        <div
                                            @click="open = !open"
                                            class="flex items-center justify-between p-4 cursor-pointer hover:bg-base-200"
                                        >
                                            <h3 class="text-lg font-medium">{{ $faq['question'] }}</h3>
                                            <i
                                                class="transition-transform duration-200 fa-solid"
                                                :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"
                                            ></i>
                                        </div>
                                        <div
                                            x-show="open"
                                            x-transition:enter="transition ease-out duration-200"
                                            x-transition:enter-start="opacity-0 transform -translate-y-2"
                                            x-transition:enter-end="opacity-100 transform translate-y-0"
                                            class="p-4 border-t border-base-300 bg-base-100"
                                        >
                                            <p class="text-base-content/80">{{ $faq['answer'] }}</p>
                                            <div class="flex justify-between mt-2">
                                                <span class="text-xs badge badge-outline">{{ $categories[$faq['category']] }}</span>
                                                <button class="text-xs btn btn-ghost btn-xs">Was this helpful?</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="p-12 text-center">
                                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-base-200">
                                    <i class="text-3xl fa-solid fa-search text-base-content/30"></i>
                                </div>
                                <h3 class="text-lg font-medium">No results found</h3>
                                <p class="mt-1 text-base-content/70">
                                    @if($searchQuery)
                                        Try different keywords or browse categories
                                    @else
                                        No FAQs available in this category
                                    @endif
                                </p>
                            </div>
                        @endif

                        @if(count($this->filteredFaqs) > 0 && $searchQuery)
                            <div class="mt-6 text-center">
                                <button
                                    wire:click="$set('searchQuery', '')"
                                    class="btn btn-outline btn-sm"
                                >
                                    Clear Search
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Contact Support Section -->
                <div class="p-6 mt-6 shadow-lg rounded-xl bg-gradient-to-r from-primary/10 to-secondary/10">
                    <div class="flex flex-col items-center gap-6 md:flex-row">
                        <div class="flex-1">
                            <h3 class="text-xl font-semibold">Can't find what you're looking for?</h3>
                            <p class="mt-2">Our support team is here to help with any questions or concerns</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="mailto:support@example.com" class="btn btn-outline btn-sm">
                                <i class="mr-1 fa-solid fa-envelope"></i>
                                Email Support
                            </a>
                            <a href="{{ route('support.create') }}" class="btn btn-primary btn-sm">
                                <i class="mr-1 fa-solid fa-ticket"></i>
                                Create Ticket
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Add this function to handle icon selection based on category
document.addEventListener('livewire:init', () => {
    Livewire.on('getCategoryIcon', (key) => {
        switch(key) {
            case 'all': return 'fa-list';
            case 'account': return 'fa-user';
            case 'billing': return 'fa-credit-card';
            case 'sessions': return 'fa-calendar';
            case 'technical': return 'fa-wrench';
            case 'reports': return 'fa-chart-line';
            default: return 'fa-circle-question';
        }
    });
});
</script>
