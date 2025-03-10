<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TeacherProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeacherProfileController extends Controller
{
    /**
     * Update teacher availability settings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'available_days' => 'required|array|min:1',
            'available_days.*' => 'required|integer|between:1,7',
            'available_time_start' => 'required|date_format:H:i',
            'available_time_end' => 'required|date_format:H:i|after:available_time_start',
            'break_time_start' => 'required|date_format:H:i',
            'break_time_end' => 'required|date_format:H:i|after:break_time_start',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teacherProfile = Auth::user()->teacherProfile;

        if (!$teacherProfile) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher profile not found.'
            ], 404);
        }

        // Update the teacher profile
        $teacherProfile->update([
            'available_days' => implode(',', $request->available_days),
            'available_time_start' => $request->available_time_start,
            'available_time_end' => $request->available_time_end,
            'break_time_start' => $request->break_time_start,
            'break_time_end' => $request->break_time_end,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Availability settings updated successfully.',
            'data' => [
                'available_days' => explode(',', $teacherProfile->available_days),
                'available_time_start' => $teacherProfile->available_time_start,
                'available_time_end' => $teacherProfile->available_time_end,
                'break_time_start' => $teacherProfile->break_time_start,
                'break_time_end' => $teacherProfile->break_time_end,
            ]
        ]);
    }
}