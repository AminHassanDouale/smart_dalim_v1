<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

class TimetableRouteServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // You would add this to your main RouteServiceProvider
        // or create a dedicated route file for teacher features

        Route::middleware(['web', 'auth', 'role:teacher'])->group(function () {
            // Timetable/Schedule route
            Volt::route('/teachers/timetable', 'teachers.timetable')
                ->name('teachers.timetable');

            // API routes for timetable
            Route::prefix('api/teachers')->name('api.teachers.')->group(function () {
                // Get sessions for a date range
                Route::get('/sessions', [\App\Http\Controllers\API\TeacherSessionController::class, 'getSessions'])
                    ->name('sessions');

                // Create a new session
                Route::post('/sessions', [\App\Http\Controllers\API\TeacherSessionController::class, 'createSession'])
                    ->name('sessions.create');

                // Update a session
                Route::put('/sessions/{session}', [\App\Http\Controllers\API\TeacherSessionController::class, 'updateSession'])
                    ->name('sessions.update');

                // Delete a session
                Route::delete('/sessions/{session}', [\App\Http\Controllers\API\TeacherSessionController::class, 'deleteSession'])
                    ->name('sessions.delete');

                // Update teacher availability
                Route::put('/availability', [\App\Http\Controllers\API\TeacherProfileController::class, 'updateAvailability'])
                    ->name('availability.update');
            });
        });
    }
}