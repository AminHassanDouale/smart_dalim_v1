<?php

use function Livewire\Volt\{state};
use Illuminate\Support\Str;
use App\Events\JoinVideoCall;

state([
    'showCreateRoomModal' => false,
    'showJoinRoomModal' => false,
    'roomId' => '',
    'joinRoomId' => '',
    'error' => ''
]);

$openCreateRoomModal = function() {
    $this->showCreateRoomModal = true;
    // Generate a unique, readable room ID (you can adjust the format as needed)
    $this->roomId = strtoupper(Str::random(10));
};

$openJoinRoomModal = function() {
    $this->showJoinRoomModal = true;
};

$createAndJoinRoom = function() {
    $this->showCreateRoomModal = false;
    event(new JoinVideoCall($this->roomId, auth()->id()));
    return $this->redirect(route('video-call', ['roomId' => $this->roomId]));
};

$joinRoom = function() {
    $this->validate([
        'joinRoomId' => 'required|string|min:10|max:10'
    ]);

    $this->showJoinRoomModal = false;
    event(new JoinVideoCall($this->joinRoomId, auth()->id()));
    return $this->redirect(route('video-call', ['roomId' => $this->joinRoomId]));
};

$copyRoomId = function() {
    // The actual copy functionality will be handled by JavaScript
    $this->dispatch('copyToClipboard', roomId: $this->roomId);
};

?>

<div>
    <!-- Call Buttons -->
    <div class="flex space-x-4">
        <x-button
            label="Create Room"
            icon="o-video-camera"
            class="btn-primary"
            wire:click="openCreateRoomModal"
        />
        <x-button
            label="Join Room"
            icon="o-arrow-right-on-rectangle"
            class="btn-secondary"
            wire:click="openJoinRoomModal"
        />
    </div>

    <!-- Create Room Modal -->
    @if($showCreateRoomModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-gray-600 opacity-50"></div>

        <div class="relative z-50 w-full max-w-md p-6 mx-auto bg-white rounded-lg shadow-xl">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Video Call Room</h3>
                <p class="mt-2 text-sm text-gray-600">Share this room ID with others to join the call:</p>

                <div class="flex items-center gap-2 mt-3">
                    <x-input
                        readonly
                        value="{{ $roomId }}"
                        class="w-full font-mono"
                    />
                    <x-button
                        icon="o-clipboard"
                        class="btn-secondary"
                        wire:click="copyRoomId"
                    />
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <x-button
                    label="Join Room"
                    class="flex-1 btn-primary"
                    wire:click="createAndJoinRoom"
                />
                <x-button
                    label="Cancel"
                    class="flex-1 btn-secondary"
                    wire:click="$set('showCreateRoomModal', false)"
                />
            </div>
        </div>
    </div>
    @endif

    <!-- Join Room Modal -->
    @if($showJoinRoomModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="fixed inset-0 bg-gray-600 opacity-50"></div>

        <div class="relative z-50 w-full max-w-md p-6 mx-auto bg-white rounded-lg shadow-xl">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Join Video Call</h3>
                <p class="mt-2 text-sm text-gray-600">Enter the room ID to join the call:</p>

                <div class="mt-3">
                    <x-input
                        wire:model="joinRoomId"
                        placeholder="Enter room ID"
                        class="w-full font-mono uppercase"
                    />
                    @error('joinRoomId')
                        <span class="text-sm text-red-500">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <x-button
                    label="Join"
                    class="flex-1 btn-primary"
                    wire:click="joinRoom"
                />
                <x-button
                    label="Cancel"
                    class="flex-1 btn-secondary"
                    wire:click="$set('showJoinRoomModal', false)"
                />
            </div>
        </div>
    </div>
    @endif
</div>

<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('copyToClipboard', async (roomId) => {
            try {
                await navigator.clipboard.writeText(roomId);
                // You might want to show a notification here
            } catch (err) {
                console.error('Failed to copy room ID:', err);
            }
        });
    });
</script>
