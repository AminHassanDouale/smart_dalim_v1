<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VideoCallController extends Controller
{
    public function __invoke(Request $request, $roomId = null)
    {
        return view('livewire.video-call', ['roomId' => $roomId]);
    }
}
