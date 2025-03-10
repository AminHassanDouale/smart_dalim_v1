<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LearningSession;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TeacherSessionController extends Controller
{
    /**
     * Get sessions for a teacher within a date range
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSessions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'subject_id' => 'nullable|exists:subjects,id',
            'course_id' => 'nullable|exists:courses,id',
            'student_id' => 'nullable|exists:childrens,id',
            'status' => 'nullable|in:scheduled,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teacherId = Auth::id();

        $query = LearningSession::with(['subject', 'children', 'course'])
            ->forTeacher($teacherId)
            ->byDateRange($request->start_date, $request->end_date);

        if ($request->has('subject_id')) {
            $query->bySubject($request->subject_id);
        }

        if ($request->has('course_id')) {
            $query->byCourse($request->course_id);
        }

        if ($request->has('student_id')) {
            $query->forStudent($request->student_id);
        }

        if ($request->has('status')) {
            $query->byStatus($request->status);
        }

        $sessions = $query->get();

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Create a new learning session
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'children_id' => 'required|exists:childrens,id',
            'subject_id' => 'required|exists:subjects,id',
            'course_id' => 'nullable|exists:courses,id',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $teacherId = Auth::id();

        // Check for scheduling conflicts
        $start = Carbon::parse($request->start_time);
        $end = Carbon::parse($request->end_time);

        $conflicts = LearningSession::forTeacher($teacherId)
            ->where('status', '!=', LearningSession::STATUS_CANCELLED)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_time', [$start, $end])
                    ->orWhereBetween('end_time', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start_time', '<=', $start)
                            ->where('end_time', '>=', $end);
                    });
            })
            ->count();

        if ($conflicts > 0) {
            return response()->json([
                'success' => false,
                'message' => 'There is a scheduling conflict with another session.'
            ], 422);
        }

        // Create the session
        $session = LearningSession::create([
            'teacher_id' => $teacherId,
            'children_id' => $request->children_id,
            'subject_id' => $request->subject_id,
            'course_id' => $request->course_id,
            'start_time' => $start,
            'end_time' => $end,
            'status' => LearningSession::STATUS_SCHEDULED,
            'location' => $request->location ?? 'Online',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session created successfully.',
            'data' => $session->load(['subject', 'children', 'course'])
        ]);
    }

    /**
     * Update an existing session
     *
     * @param Request $request
     * @param LearningSession $session
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSession(Request $request, LearningSession $session)
    {
        // Check if the session belongs to this teacher
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this session.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'children_id' => 'nullable|exists:childrens,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'course_id' => 'nullable|exists:courses,id',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'status' => 'nullable|in:scheduled,completed,cancelled',
            'attended' => 'nullable|boolean',
            'location' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for scheduling conflicts if times are being updated
        if ($request->has('start_time') || $request->has('end_time')) {
            $start = Carbon::parse($request->start_time ?? $session->start_time);
            $end = Carbon::parse($request->end_time ?? $session->end_time);

            $conflicts = LearningSession::forTeacher(Auth::id())
                ->where('id', '!=', $session->id)
                ->where('status', '!=', LearningSession::STATUS_CANCELLED)
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('start_time', [$start, $end])
                        ->orWhereBetween('end_time', [$start, $end])
                        ->orWhere(function ($query) use ($start, $end) {
                            $query->where('start_time', '<=', $start)
                                ->where('end_time', '>=', $end);
                        });
                })
                ->count();

            if ($conflicts > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'There is a scheduling conflict with another session.'
                ], 422);
            }
        }

        // Update the session
        $session->update($request->only([
            'children_id',
            'subject_id',
            'course_id',
            'start_time',
            'end_time',
            'status',
            'attended',
            'location',
            'notes',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Session updated successfully.',
            'data' => $session->fresh()->load(['subject', 'children', 'course'])
        ]);
    }

    /**
     * Delete (cancel) a session
     *
     * @param LearningSession $session
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSession(LearningSession $session)
    {
        // Check if the session belongs to this teacher
        if ($session->teacher_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this session.'
            ], 403);
        }

        // If the session is in the future, mark it as cancelled
        if ($session->start_time->isFuture()) {
            $session->markAsCancelled('Cancelled by teacher');

            return response()->json([
                'success' => true,
                'message' => 'Session cancelled successfully.'
            ]);
        }

        // If it's in the past, we don't allow deletion
        return response()->json([
            'success' => false,
            'message' => 'Cannot cancel a past session.'
        ], 422);
    }
}
