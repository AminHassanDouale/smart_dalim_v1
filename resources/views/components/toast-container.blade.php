<div
    x-data="{
        toasts: [],
        add(toast) {
            this.toasts.push(toast);
            setTimeout(() => {
                this.remove(toast);
            }, toast.timeout || 5000);
        },
        remove(toastToRemove) {
            this.toasts = this.toasts.filter(toast => toast !== toastToRemove);
        }
    }"
    x-on:toast.window="add($event.detail)"
    class="fixed inset-0 z-50 flex flex-col-reverse items-end justify-start p-4 space-y-4 space-y-reverse overflow-hidden pointer-events-none"
>
    <template x-for="(toast, index) in toasts" :key="index">
        <div
            x-transition:enter="transform ease-out duration-300 transition"
            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            :class="toast.css || 'alert-info'"
            class="flex items-start max-w-md mb-3 shadow-lg pointer-events-auto alert"
        >
            <div class="flex-1">
                <!-- Icon -->
                <div x-show="toast.icon" class="flex-shrink-0">
                    <i x-show="toast.icon" :class="toast.icon"></i>
                </div>

                <!-- Content -->
                <div>
                    <h3 x-text="toast.title" class="font-bold"></h3>
                    <div x-text="toast.description" class="text-sm"></div>
                </div>
            </div>

            <!-- Close button -->
            <button
                @click="remove(toast)"
                class="btn btn-sm btn-ghost"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>
