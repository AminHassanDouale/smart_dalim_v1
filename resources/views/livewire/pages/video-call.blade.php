<?php

use function Livewire\Volt\{state};
use App\Events\{JoinVideoCall, LeaveVideoCall};
use Illuminate\Support\Str;

state(['roomId' => $roomId ?? Str::uuid(), 'localStream' => null, 'peers' => []]);

$createRoom = function() {
    $this->roomId = Str::uuid();
    $this->dispatch('room-created', $this->roomId);
};

$joinRoom = function($roomId) {
    event(new JoinVideoCall($roomId, auth()->id()));
};

$leaveRoom = function() {
    event(new LeaveVideoCall($this->roomId, auth()->id()));
    $this->reset(['roomId', 'localStream', 'peers']);
};
?>

<div class="min-h-screen p-6 bg-gray-100">
    <div class="mx-auto max-w-7xl">
        @if(!$roomId)
            <div class="text-center">
                <h2 class="mb-4 text-2xl font-bold">Start a Video Call</h2>
                <button
                    wire:click="createRoom"
                    class="px-4 py-2 text-white bg-blue-500 rounded-lg hover:bg-blue-600"
                >
                    Create New Room
                </button>
            </div>
        @else
            <div class="p-6 bg-white rounded-lg shadow-lg">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold">Room: {{ $roomId }}</h2>
                    <button
                        wire:click="leaveRoom"
                        class="px-4 py-2 text-white bg-red-500 rounded-lg hover:bg-red-600"
                    >
                        Leave Room
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Local Video -->
                    <div class="relative">
                        <video
                            id="localVideo"
                            class="w-full bg-black rounded-lg"
                            autoplay
                            muted
                            playsinline
                        ></video>
                        <span class="absolute px-2 py-1 text-white bg-black bg-opacity-50 rounded bottom-2 left-2">
                            You
                        </span>
                    </div>

                    <!-- Remote Videos -->
                    <template x-for="peer in peers" :key="peer.id">
                        <div class="relative">
                            <video
                                :id="'remoteVideo-' + peer.id"
                                class="w-full bg-black rounded-lg"
                                autoplay
                                playsinline
                            ></video>
                            <span class="absolute px-2 py-1 text-white bg-black bg-opacity-50 rounded bottom-2 left-2"
                                x-text="peer.name"
                            ></span>
                        </div>
                    </template>
                </div>

                <!-- Invite Link -->
                <div class="mt-6">
                    <h3 class="mb-2 text-lg font-medium">Invite Others</h3>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            readonly
                            value="{{ route('video-call', ['roomId' => $roomId]) }}"
                            class="flex-1 px-3 py-2 border rounded-lg"
                        >
                        <button
                            onclick="navigator.clipboard.writeText('{{ route('video-call', ['roomId' => $roomId]) }}')"
                            class="px-4 py-2 text-white bg-gray-500 rounded-lg hover:bg-gray-600"
                        >
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @script
    <script>
        let localStream;
        let peerConnections = {};

        // Initialize WebRTC
        async function initializeWebRTC() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({
                    video: true,
                    audio: true
                });

                document.getElementById('localVideo').srcObject = localStream;

                // Connect to WebSocket server
                Echo.private('video-call.' + @js($roomId))
                    .listen('JoinVideoCall', (e) => handlePeerJoined(e.userId))
                    .listen('LeaveVideoCall', (e) => handlePeerLeft(e.userId));

            } catch (error) {
                console.error('Error accessing media devices:', error);
            }
        }

        // Handle new peer joining
        async function handlePeerJoined(userId) {
            const peerConnection = new RTCPeerConnection({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' }
                ]
            });

            peerConnections[userId] = peerConnection;

            // Add local stream
            localStream.getTracks().forEach(track => {
                peerConnection.addTrack(track, localStream);
            });

            // Handle ICE candidates
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    Echo.private('video-call.' + @js($roomId))
                        .whisper('ice-candidate', {
                            userId: userId,
                            candidate: event.candidate
                        });
                }
            };

            // Handle remote stream
            peerConnection.ontrack = (event) => {
                const remoteVideo = document.getElementById('remoteVideo-' + userId);
                if (remoteVideo) {
                    remoteVideo.srcObject = event.streams[0];
                }
            };

            // Create and send offer
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);

            Echo.private('video-call.' + @js($roomId))
                .whisper('video-offer', {
                    userId: userId,
                    offer: offer
                });
        }

        // Handle peer leaving
        function handlePeerLeft(userId) {
            if (peerConnections[userId]) {
                peerConnections[userId].close();
                delete peerConnections[userId];

                const remoteVideo = document.getElementById('remoteVideo-' + userId);
                if (remoteVideo) {
                    remoteVideo.srcObject = null;
                }
            }
        }

        // Initialize when component loads
        if (@js($roomId)) {
            initializeWebRTC();
        }
    </script>
    @endscript
</div>
