<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Middleware\CheckRole;

// Authentication Routes
Volt::route('/', 'welcome')->name('welcome');
Volt::route('/login', 'login')->name('login');
Volt::route('/register', 'register')->name('register');



// Password Reset Routes
Volt::route('/forgot-password', 'auth.forgot-password')
    ->name('password.request');
Volt::route('/reset-password/{token}', 'auth.reset-password')
    ->name('password.reset');
Volt::route('/password/email', 'auth.forgot-password')
    ->name('password.email');

// Logout Route
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Parent Routes
Route::middleware(['auth', 'role:parent'])->group(function () {
    // Profile Setup Route
    Volt::route('parents/dashboard', 'parents.dashboard')
        ->name('parents.dashboard');

    // Profile Setup and Management
    Volt::route('/parents/profile-setup', 'parents.profile-setup.steps')
        ->name('parents.profile-setup');

    Volt::route('/parents/profile', 'parents.profile')
        ->name('parents.profile');

    Volt::route('/{user}/edit', 'parents.profile.edit')
        ->name('parents.profile.edit');
        Volt::route('/parents/notification-preferences', 'parents.notification-preferences')
    ->name('parents.notification-preferences');

    // Children Management
    Volt::route('/children', 'parents.children.index')
        ->name('parents.children.index');

    Volt::route('/children/create', 'parents.children.create')
        ->name('parents.children.create');

    Volt::route('/children/{child}', 'parents.children.show')
        ->name('parents.children.show');

    Volt::route('/children/{child}/edit', 'parents.children.edit')
        ->name('parents.children.edit');

    // Calendar and Schedule Management
    Volt::route('parents/calendar', 'parents.calendar')
        ->name('parents.calendar');

    Volt::route('/schedule', 'parents.schedule.index')
        ->name('parents.schedule.index');

    // Learning Progress and Reports
    Volt::route('/progress', 'parents.progress.index')
        ->name('parents.progress.index');

    Volt::route('/progress/{child}', 'parents.progress.child')
        ->name('parents.progress.child');

    Volt::route('/reports', 'parents.reports.index')
        ->name('parents.reports.index');

    Volt::route('/reports/{child}', 'parents.reports.child')
        ->name('reports.child');

    // Sessions and Lessons
    Volt::route('/sessions', 'parents.sessions.index')
        ->name('parents.sessions.index');

    Volt::route('/sessions/{session}', 'parents.sessions.show')
        ->name('sessions.show');

    Volt::route('/session-requests', 'parents.sessions.requests')
        ->name('parents.sessions.requests');

    // Assessments and Homework
    Volt::route('/assessments/{childId?}', 'parents.assessments.index')
    ->name('parents.assessments.index');

        Volt::route('/assessments/{id}/child/{childId}', 'parents.assessments.show')
        ->name('parents.assessments.show');

    Volt::route('/homework', 'parents.homework.index')
        ->name('parents.homework.index');

    Volt::route('/homework/{homework}', 'parents.homework.show')
        ->name('parents.homework.show');

    // Learning Materials
    Volt::route('/materials', 'parents.materials.index')
        ->name('parents.materials.index');

    Volt::route('/materials/{material}', 'parents.materials.show')
        ->name('materials.show');

    // Billing and Payments
        Volt::route('/billing', 'parents.billing.index')
            ->name('parents.billing.index');

    Volt::route('/payments', 'parents.payments.index')
        ->name('parents.payments.index');

    Volt::route('/invoices', 'parents.invoices.index')
        ->name('parents.invoices.index');

    Volt::route('/invoices/{invoice}', 'parents.invoices.show')
        ->name('invoices.show');

    // Communications
    Volt::route('/messages', 'parents.messages.index')
        ->name('parents.messages.index');

    Volt::route('/messages/{conversation}', 'parents.messages.show')
        ->name('messages.show');

    Volt::route('/notifications', 'parents.notifications.index')
        ->name('notifications.index');

    // Support and Help
    Volt::route('/support', 'parents.support.index')
        ->name('parents.support.index');

    Volt::route('/support/create', 'parents.support.create')
        ->name('support.create');

    Volt::route('/support/{ticket}', 'parents.support.show')
        ->name('support.show');

    Volt::route('/faq', 'parents.faq')
        ->name('faq');
});

// Teacher Routes
// Teacher Routes
Route::middleware(['auth', 'role:teacher'])->group(function () {
    // Profile Setup
    Volt::route('/teachers/profile-setup', 'teachers.profile-setup')
        ->name('teachers.profile-setup');

    // Profile Management
    Volt::route('/teachers/profile', 'teachers.profile.show')
        ->name('teachers.profile');
    Volt::route('/teachers/{teacher}/edit', 'teachers.profile.edit')
        ->name('teachers.profile.edit');

    // Dashboard
    Volt::route('/teachers/dashboard', 'teachers.dashboard')
        ->name('teachers.dashboard');

    // Course Management
    Volt::route('/teachers/courses', 'teachers.courses.index')
        ->name('teachers.courses');
    Volt::route('/teachers/courses/create', 'teachers.courses.create')
        ->name('teachers.courses.create');
    Volt::route('/teachers/courses/{course}', 'teachers.courses.show')
        ->name('teachers.courses.show');
    Volt::route('/teachers/courses/{course}/edit', 'teachers.courses.edit')
        ->name('teachers.courses.edit');

    // Student Management
    Volt::route('/teachers/students', 'teachers.students.index')
        ->name('teachers.students.index');
    Volt::route('/teachers/students/{student}', 'teachers.students.show')
        ->name('teachers.students.show');

    // Session Management
    Volt::route('/teachers/sessions', 'teachers.sessions.index')
        ->name('teachers.sessions');
    Volt::route('/teachers/sessions/{session}', 'teachers.sessions.show')
        ->name('teachers.sessions.show');

    // Session Requests
    Volt::route('/teachers/session-requests', 'teachers.session-requests.index')
    ->name('teachers.session-requests')
    ->middleware(['auth', 'verified', 'role:teacher']);

    // Timetable/Schedule
    Volt::route('/teachers/timetable', 'teachers.timetable')
        ->name('teachers.timetable');

    // Curriculum Management
    Volt::route('/teachers/curriculum', 'teachers.curriculum')
        ->name('teachers.curriculum');

    // Materials & Resources
    Volt::route('/teachers/materials', 'teachers.materials.index')
        ->name('teachers.materials.index');
    Volt::route('/teachers/materials/create', 'teachers.materials.create')
        ->name('teachers.materials.create');
    Volt::route('/teachers/materials/{material}', 'teachers.materials.show')
        ->name('teachers.materials.show');
    Volt::route('/teachers/materials/{material}/edit', 'teachers.materials.edit')
        ->name('teachers.materials.edit');

    // Assessments & Grading
   // Assessments & Grading
Volt::route('/teachers/assessments', 'teachers.assessments.index')
->name('teachers.assessments.index');

Volt::route('/teachers/assessments/create', 'teachers.assessments.create')
->name('teachers.assessments.create');

Volt::route('/teachers/assessments/reviews', 'teachers.assessments.reviews')
->name('teachers.assessments.reviews')
->middleware(['auth', 'verified', 'role:teacher']);

Volt::route('/teachers/assessments/reports', 'teachers.assessments.reports')
->name('teachers.assessments.reports')
->middleware(['auth', 'verified', 'role:teacher']);

Volt::route('/teachers/assessments/question-bank', 'teachers.assessments.question-bank')
->name('teachers.assessments.question-bank')
->middleware(['auth', 'verified', 'role:teacher']);

// This should be LAST - parameter route comes after specific routes
Volt::route('/teachers/assessments/{assessment}', 'teachers.assessments.show')
->name('teachers.assessments.show')
->middleware(['auth', 'role:teacher'])
->where('assessment', '[0-9]+'); // Only match numeric IDs
});


//students


// Client Routes
// Client Routes with Multi-step Profile Setup
Route::middleware(['auth', 'role:client'])->group(function () {
      // Profile Setup Route
      Volt::route('/clients/profile-setup', 'clients.profile-setup')
      ->name('clients.profile-setup');

  // Dashboard
  Volt::route('/clients/dashboard', 'clients.dashboard')
      ->name('clients.dashboard');

  // Profile and other pages
  Volt::route('/clients/profile', 'clients.profile')
      ->name('clients.profile');

  Volt::route('/clients/{user}/edit', 'clients.profile.edit')
      ->name('clients.profile.edit');

  // Course and enrollment routes
  Volt::route('/clients/courses', 'clients.courses')
      ->name('clients.courses');

  Volt::route('/clients/enrollments', 'clients.enrollments')
      ->name('clients.enrollments');

  Volt::route('/clients/courses/{course}', 'clients.courses.show')
      ->name('clients.courses.show');

  Volt::route('/clients/enrollments/{enrollment}', 'clients.enrollments.show')
      ->name('clients.enrollments.show');

  // Learning sessions
  Volt::route('/clients/sessions', 'clients.sessions')
      ->name('clients.sessions');

  Volt::route('/clients/sessions/{session}', 'clients.sessions.show')
      ->name('clients.sessions.show');

  Volt::route('/clients/session-requests', 'clients.session-requests')
      ->name('clients.session-requests');
      Volt::route('/clients/wishlist', 'clients.wishlist')
      ->name('clients.wishlist');






});




// Admin Routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Volt::route('/dashboard', 'admin.dashboard')
    ->name('admin.dashboard');

// User Management
Route::prefix('users')->group(function () {
    Volt::route('/', 'admin.users.index')
        ->name('admin.users.index');
    Volt::route('/create', 'admin.users.create')
        ->name('admin.users.create');
    Volt::route('/{user}', 'admin.users.show')
        ->name('admin.users.show');
    Volt::route('/{user}/edit', 'admin.users.edit')
        ->name('admin.users.edit');

    // Verification Management
    Volt::route('/verifications', 'admin.users.verifications.index')
        ->name('admin.users.verifications.index');
    Volt::route('/verifications/{id}', 'admin.users.verifications.show')
        ->name('admin.users.verifications.show');
});

// Teacher Management
Route::prefix('teachers')->group(function () {
    Volt::route('/', 'admin.teachers.index')
        ->name('admin.teachers.index');
    Volt::route('/create', 'admin.teachers.create')
        ->name('admin.teachers.create');
    Volt::route('/{teacher}', 'admin.teachers.show')
        ->name('admin.teachers.show');
    Volt::route('/{teacher}/edit', 'admin.teachers.edit')
        ->name('admin.teachers.edit');
});

// Parent Management
Route::prefix('parents')->group(function () {
    Volt::route('/', 'admin.parents.index')
        ->name('admin.parents.index');
    Volt::route('/create', 'admin.parents.create')
        ->name('admin.parents.create');
    Volt::route('/{parent}', 'admin.parents.show')
        ->name('admin.parents.show');
    Volt::route('/{parent}/edit', 'admin.parents.edit')
        ->name('admin.parents.edit');
});

// Client Management
Route::prefix('clients')->group(function () {
    Volt::route('/', 'admin.clients.index')
        ->name('admin.clients.index');
    Volt::route('/create', 'admin.clients.create')
        ->name('admin.clients.create');
    Volt::route('/{client}', 'admin.clients.show')
        ->name('admin.clients.show');
    Volt::route('/{client}/edit', 'admin.clients.edit')
        ->name('admin.clients.edit');
});
    // Academic Management
    Route::prefix('academic')->group(function () {
        // Subjects
        Volt::route('/subjects', 'admin.academic.subjects.index')
            ->name('admin.academic.subjects.index');
        Volt::route('/subjects/create', 'admin.academic.subjects.create')
            ->name('admin.academic.subjects.create');
        Volt::route('/subjects/{subject}/edit', 'admin.academic.subjects.edit')
            ->name('admin.academic.subjects.edit');

        // Courses
        Volt::route('/courses', 'admin.academic.courses.index')
            ->name('admin.academic.courses.index');
        Volt::route('/courses/{course}', 'admin.academic.courses.show')
            ->name('admin.academic.courses.show');
        Volt::route('/courses/{course}/edit', 'admin.academic.courses.edit')
            ->name('admin.academic.courses.edit');

        // Materials
        Volt::route('/materials', 'admin.academic.materials.index')
            ->name('admin.academic.materials.index');
        Volt::route('/materials/{material}', 'admin.academic.materials.show')
            ->name('admin.academic.materials.show');

        // Assessments
        Volt::route('/assessments', 'admin.academic.assessments.index')
            ->name('admin.academic.assessments.index');
        Volt::route('/assessments/{assessment}', 'admin.academic.assessments.show')
            ->name('admin.academic.assessments.show');
    });

    // Learning Sessions Management
    Route::prefix('sessions')->group(function () {
        Volt::route('/', 'admin.sessions.index')
            ->name('admin.sessions.index');
        Volt::route('/calendar', 'admin.sessions.calendar')
            ->name('admin.sessions.calendar');
        Volt::route('/requests', 'admin.sessions.requests')
            ->name('admin.sessions.requests');
        Volt::route('/{session}', 'admin.sessions.show')
            ->name('admin.sessions.show');
    });

    // Financial Management
    Route::prefix('finance')->group(function () {
        Volt::route('/dashboard', 'admin.finance.dashboard')
            ->name('admin.finance.dashboard');

        // Invoices
        Volt::route('/invoices', 'admin.finance.invoices.index')
            ->name('admin.finance.invoices.index');
        Volt::route('/invoices/create', 'admin.finance.invoices.create')
            ->name('admin.finance.invoices.create');
        Volt::route('/invoices/{invoice}', 'admin.finance.invoices.show')
            ->name('admin.finance.invoices.show');
        Volt::route('/invoices/{invoice}/edit', 'admin.finance.invoices.edit')
            ->name('admin.finance.invoices.edit');

        // Payments
        Volt::route('/payments', 'admin.finance.payments.index')
            ->name('admin.finance.payments.index');
        Volt::route('/payments/{payment}', 'admin.finance.payments.show')
            ->name('admin.finance.payments.show');

        // Reports
        Volt::route('/reports', 'admin.finance.reports.index')
            ->name('admin.finance.reports.index');
        Volt::route('/reports/revenue', 'admin.finance.reports.revenue')
            ->name('admin.finance.reports.revenue');
        Volt::route('/reports/transactions', 'admin.finance.reports.transactions')
            ->name('admin.finance.reports.transactions');
    });

    // Support System
    Route::prefix('support')->group(function () {
        Volt::route('/tickets', 'admin.support.tickets.index')
            ->name('admin.support.tickets.index');
        Volt::route('/tickets/{ticket}', 'admin.support.tickets.show')
            ->name('admin.support.tickets.show');

        // FAQ Management
        Volt::route('/faq', 'admin.support.faq.index')
            ->name('admin.support.faq.index');
        Volt::route('/faq/create', 'admin.support.faq.create')
            ->name('admin.support.faq.create');
        Volt::route('/faq/{faq}/edit', 'admin.support.faq.edit')
            ->name('admin.support.faq.edit');
    });

    // Communications
    Route::prefix('communications')->group(function () {
        // Messages
        Volt::route('/messages', 'admin.communications.messages.index')
            ->name('admin.communications.messages.index');
        Volt::route('/messages/{conversation}', 'admin.communications.messages.show')
            ->name('admin.communications.messages.show');

        // Notifications
        Volt::route('/notifications', 'admin.communications.notifications.index')
            ->name('admin.communications.notifications.index');
        Volt::route('/notifications/create', 'admin.communications.notifications.create')
            ->name('admin.communications.notifications.create');

        // Announcements
        Volt::route('/announcements', 'admin.communications.announcements.index')
            ->name('admin.communications.announcements.index');
        Volt::route('/announcements/create', 'admin.communications.announcements.create')
            ->name('admin.communications.announcements.create');
        Volt::route('/announcements/{announcement}/edit', 'admin.communications.announcements.edit')
            ->name('admin.communications.announcements.edit');
    });

    // Analytics & Reports
    Route::prefix('analytics')->group(function () {
        Volt::route('/dashboard', 'admin.analytics.dashboard')
            ->name('admin.analytics.dashboard');
        Volt::route('/users', 'admin.analytics.users')
            ->name('admin.analytics.users');
        Volt::route('/sessions', 'admin.analytics.sessions')
            ->name('admin.analytics.sessions');
        Volt::route('/courses', 'admin.analytics.courses')
            ->name('admin.analytics.courses');
        Volt::route('/assessments', 'admin.analytics.assessments')
            ->name('admin.analytics.assessments');
        Volt::route('/export/{type}', 'admin.analytics.export')
            ->name('admin.analytics.export');
    });

    // System Settings
    Route::prefix('settings')->group(function () {
        Volt::route('/general', 'admin.settings.general')
            ->name('admin.settings.general');
        Volt::route('/appearance', 'admin.settings.appearance')
            ->name('admin.settings.appearance');
        Volt::route('/email', 'admin.settings.email')
            ->name('admin.settings.email');
        Volt::route('/integrations', 'admin.settings.integrations')
            ->name('admin.settings.integrations');
        Volt::route('/roles-permissions', 'admin.settings.roles')
            ->name('admin.settings.roles');
        Volt::route('/backup', 'admin.settings.backup')
            ->name('admin.settings.backup');
        Volt::route('/logs', 'admin.settings.logs')
            ->name('admin.settings.logs');
    });

    // Media Library
    Route::prefix('media')->group(function () {
        Volt::route('/', 'admin.media.index')
            ->name('admin.media.index');
        Volt::route('/upload', 'admin.media.upload')
            ->name('admin.media.upload');
        Volt::route('/{file}', 'admin.media.show')
            ->name('admin.media.show');
    });

    // Monitoring & Logs
    Route::prefix('monitoring')->group(function () {
        Volt::route('/activity-logs', 'admin.monitoring.activity')
            ->name('admin.monitoring.activity');
        Volt::route('/system-health', 'admin.monitoring.health')
            ->name('admin.monitoring.health');
        Volt::route('/audit-trail', 'admin.monitoring.audit')
            ->name('admin.monitoring.audit');
    });

    // Profile Management
    Volt::route('/profile', 'admin.profile.show')
        ->name('admin.profile.show');
    Volt::route('/profile/edit', 'admin.profile.edit')
        ->name('admin.profile.edit');
    Volt::route('/profile/security', 'admin.profile.security')
        ->name('admin.profile.security');
});
//quran
// Quran Routes
//Route::middleware(['auth'])->group(function () {
//    Volt::route('/recites', 'recites.index')
//        ->name('recites.index');
//
//    Volt::route('/recites/chapter/{id}', 'recites.chapter')
//        ->name('recites.chapter');
//
//    // Page-specific routes
//    Volt::route('/recites/page/{page}', 'recites.verse-by-page')
//        ->name('recites.page')
//        ->where('page', '[1-9][0-9]*');
//
//    // Chapter-specific page routes
//    Volt::route('/recites/chapter/{chapter}/page/{page}', 'recites.verse-by-page')
//        ->name('recites.chapter.page')
//        ->where(['chapter' => '[1-9][0-9]*', 'page' => '[1-9][0-9]*']);
//});
//Route::middleware(['auth'])->group(function () {
//    Volt::route('/hadiths', 'hadiths.index')->name('hadiths.index');
//    Volt::route('/hadiths/{edition}', 'hadiths.show')->name('hadiths.show');
//
//});
//
//Route::middleware(['auth'])->group(function () {
//    Volt::route('/books', 'books.index')->name('books.index');
//    Volt::route('/books/create', 'books.create')->name('books.create');
//    Volt::route('/books/show/{id}', 'books.show')->name('books.show');
//
//});
//
//// Store Routes (if needed)
//Route::middleware('auth')->group(function () {
//    Volt::route('video-call/{roomId?}', 'video-call')
//    ->name('video-call');
//    Volt::route('/cart', 'store.cart.index')->name('cart');
//    Volt::route('/wishlist', 'store.wishlist.index')->name('wishlist');
//    Volt::route('/checkout', 'store.checkout.index')->name('checkout');
//    Volt::route('/orders', 'store.orders.index')->name('orders');
//    Volt::route('/orders/{order}', 'store.orders.show')->name('orders.show');
//    Volt::route('/home', 'store.index')->name('home');
//    Volt::route('/products/{product}', 'store.products.show')->name('products.show');
//    Volt::route('/support-us', 'support-us')->name('support');
//    Volt::route('/test-broadcast', 'test-broadcast')
//    ->name('test-broadcast');
//});
