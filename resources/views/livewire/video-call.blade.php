<?php

use function Livewire\Volt\{state};
use Illuminate\Support\Facades\Auth;
use App\Events\JoinVideoCall;
use App\Events\LeaveVideoCall;

state(['roomId', 'user', 'participantCount' => 0]);

$mount = function($roomId = null) {
    $this->roomId = $roomId;
    $this->user = Auth::user();

    if (!$this->roomId) {
        return redirect()->route('dashboard');
    }

    // Broadcast join event when component mounts
    event(new JoinVideoCall($this->roomId, $this->user->id));
};

$endCall = function() {
    event(new LeaveVideoCall($this->roomId, $this->user->id));
    return redirect()->route($this->user->getDashboardRoute());
};

$getListeners = function() {
    return [
        "echo:video-call.{$this->roomId},JoinVideoCall" => 'handleJoinCall',
        "echo:video-call.{$this->roomId},LeaveVideoCall" => 'handleLeaveCall'
    ];
};

$handleJoinCall = function($event) {
    if ($event['userId'] !== $this->user->id) {
        $this->participantCount++;
        $this->dispatch('userJoined', $event['userId']);
    }
};

$handleLeaveCall = function($event) {
    if ($event['userId'] !== $this->user->id) {
        $this->participantCount--;
        $this->dispatch('userLeft', $event['userId']);
    }
};

?>

<div class="min-h-screen bg-gray-100">
    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-2xl font-semibold">Video Call Room</h2>
                            <p class="text-sm text-gray-600">Room ID: {{ $roomId }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-600">
                                {{ $participantCount }} participant(s)
                            </span>
                            <x-button
                                label="Leave Call"
                                icon="o-phone-x-mark"
                                class="btn-error"
                                wire:click="endCall"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <!-- Local Video -->
                        <div class="relative overflow-hidden bg-black rounded-lg aspect-video">
                            <video id="localVideo" autoplay playsinline muted class="object-cover w-full h-full"></video>
                            <div class="absolute bottom-0 left-0 p-2 text-white bg-black bg-opacity-50">
                                {{ $user->name }} (You)
                            </div>
                        </div>

                        <!-- Remote Video -->
                        <div class="relative overflow-hidden bg-black rounded-lg aspect-video">
                            <video id="remoteVideo" autoplay playsinline class="object-cover w-full h-full"></video>
                            <div id="waitingMessage" class="absolute inset-0 flex items-center justify-center text-white">
                                {{ $participantCount > 0 ? 'Connecting...' : 'Waiting for others to join...' }}
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center mt-6 space-x-4">
                        <x-button
                            id="toggleAudio"
                            icon="o-microphone"
                            class="btn-secondary"
                        />
                        <x-button
                            id="toggleVideo"
                            icon="o-video-camera"
                            class="btn-secondary"
                        />
                        <x-button
                            id="copyRoomId"
                            icon="o-clipboard"
                            label="Copy Room ID"
                            class="btn-secondary"
                            x-data
                            x-on:click="navigator.clipboard.writeText('{{ $roomId }}')"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// WebRTC configuration code remains the same as in the previous example
let peerConnection;
let localStream;
const servers = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' }
    ]
};

// Initialize media stream and WebRTC
async function initCall() {
    try {
        localStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
        });
        document.getElementById('localVideo').srcObject = localStream;

        peerConnection = new RTCPeerConnection(servers);

        // Add local tracks to the connection
        localStream.getTracks().forEach(track => {
            peerConnection.addTrack(track, localStream);
        });

        // Handle incoming tracks
        peerConnection.ontrack = (event) => {
            document.getElementById('remoteVideo').srcObject = event.streams[0];
            document.getElementById('waitingMessage').style.display = 'none';
        };

        // Handle ICE candidates
        peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                Echo.private(`video-call.${@json($roomId)}`)
                    .whisper('ice-candidate', {
                        userId: @json($user->id),
                        candidate: event.candidate
                    });
            }
        };

        setupControlButtons();
    } catch (error) {
        console.error('Error initializing call:', error);
        alert('Could not access camera or microphone');
    }
}

function setupControlButtons() {
    const toggleAudio = document.getElementById('toggleAudio');
    const toggleVideo = document.getElementById('toggleVideo');

    toggleAudio.addEventListener('click', () => {
        const audioTrack = localStream.getAudioTracks()[0];
        audioTrack.enabled = !audioTrack.enabled;
        toggleAudio.classList.toggle('btn-error', !audioTrack.enabled);
    });

    toggleVideo.addEventListener('click', () => {
        const videoTrack = localStream.getVideoTracks()[0];
        videoTrack.enabled = !videoTrack.enabled;
        toggleVideo.classList.toggle('btn-error', !videoTrack.enabled);
    });
}

// Handle user joined event
Livewire.on('userJoined', async (userId) => {
    try {
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        Echo.private(`video-call.${@json($roomId)}`)
            .whisper('offer', {
                userId: @json($user->id),
                offer: offer
            });
    } catch (error) {
        console.error('Error creating offer:', error);
    }
});

// Listen for WebRTC signaling messages
Echo.private(`video-call.${@json($roomId)}`)
    .listenForWhisper('offer', async (data) => {
        if (data.userId !== @json($user->id)) {
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);

                Echo.private(`video-call.${@json($roomId)}`)
                    .whisper('answer', {
                        userId: @json($user->id),
                        answer: answer
                    });
            } catch (error) {
                console.error('Error handling offer:', error);
            }
        }
    })
    .listenForWhisper('answer', async (data) => {
        if (data.userId !== @json($user->id)) {
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer));
            } catch (error) {
                console.error('Error handling answer:', error);
            }
        }
    })
    .listenForWhisper('ice-candidate', async (data) => {
        if (data.userId !== @json($user->id)) {
            try {
                await peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate));
            } catch (error) {
                console.error('Error adding ICE candidate:', error);
            }
        }
    });

// Initialize the call when the page loads
document.addEventListener('DOMContentLoaded', initCall);

// Clean up when leaving
window.addEventListener('beforeunload', () => {
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
    if (peerConnection) {
        peerConnection.close();
    }
});
</script>
