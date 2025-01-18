<?php

use function Livewire\Volt\{state};
use App\Events\TestBroadcast;

state([
    'messages' => [],
    'newMessage' => ''
]);

$sendMessage = function() {
    $message = $this->newMessage;
    event(new TestBroadcast($message));
    $this->messages[] = ['text' => $message, 'type' => 'sent'];
    $this->newMessage = '';
};

$getListeners = function() {
    return [
        'echo:test-channel,TestBroadcast' => 'handleBroadcast'
    ];
};

$handleBroadcast = function($event) {
    $this->messages[] = [
        'text' => $event['message'],
        'type' => 'received'
    ];
};

?>

<div class="min-h-screen py-12 bg-gray-100">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div class="p-6">
                <h2 class="mb-4 text-2xl font-semibold">Broadcasting Test</h2>

                <!-- Messages Display -->
                <div class="h-64 p-4 mb-4 overflow-y-auto border rounded-lg">
                    @foreach($messages as $message)
                        <div class="mb-2 {{ $message['type'] === 'sent' ? 'text-right' : 'text-left' }}">
                            <span class="inline-block px-3 py-1 rounded-lg {{ $message['type'] === 'sent' ? 'bg-blue-500 text-white' : 'bg-gray-200' }}">
                                {{ $message['text'] }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <!-- Message Input -->
                <form wire:submit="sendMessage" class="flex gap-2">
                    <x-input
                        wire:model="newMessage"
                        class="flex-1"
                        placeholder="Type a message to test broadcasting..."
                    />
                    <x-button
                        type="submit"
                        label="Send"
                        class="btn-primary"
                    />
                </form>

                <!-- Connection Status -->
                <div
                    x-data="{ status: 'connecting' }"
                    x-init="
                        window.Echo.connector.pusher.connection.bind('connected', () => status = 'connected');
                        window.Echo.connector.pusher.connection.bind('disconnected', () => status = 'disconnected');
                        window.Echo.connector.pusher.connection.bind('error', () => status = 'error');
                    "
                    class="mt-4 text-sm"
                >
                    <span>Pusher Status:</span>
                    <span
                        x-text="status"
                        :class="{
                            'text-green-600': status === 'connected',
                            'text-yellow-600': status === 'connecting',
                            'text-red-600': status === 'disconnected' || status === 'error'
                        }"
                    ></span>
                </div>
            </div>
        </div>
    </div>
</div>
