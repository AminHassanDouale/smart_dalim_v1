<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        // Check if user is logged in
        if (!Auth::check()) {
            return redirect()->route('login')
                ->with('error', 'Please login to access this page.');
        }

        $user = Auth::user();

        // Check if user has the required role
        if ($user->role !== $role) {
            // Redirect based on user's actual role
            if ($user->role === 'parent') {
                return redirect()->route('parents.dashboard')
                    ->with('error', 'You do not have permission to access that page.');
            }

            if ($user->role === 'teacher') {
                return redirect()->route('teachers.dashboard')
                    ->with('error', 'You do not have permission to access that page.');
            }

            return redirect()->route('login');
        }

        // Skip profile check for profile setup routes
        if ($request->routeIs('*.profile-setup')) {
            return $next($request);
        }

        // Check if profile exists based on role
        if ($role === 'parent' && !$user->parentProfile()->exists()) {
            return redirect()->route('parents.profile-setup')
                ->with('warning', 'Please complete your profile first.');
        }

        if ($role === 'teacher' && !$user->teacherProfile()->exists()) {
            return redirect()->route('teachers.profile-setup')
                ->with('warning', 'Please complete your profile first.');
        }

        return $next($request);
    }
}
