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
    Volt::route('/dashboard', 'parents.dashboard')
        ->name('dashboard');

    // Profile Setup and Management
    Volt::route('/profile-setup', 'parents.profile-setup.steps')
        ->name('profile-setup');

    Volt::route('/profile', 'parents.profile')
        ->name('profile');

    Volt::route('/{user}/edit', 'parents.profile.edit')
        ->name('profile.edit');

    // Children Management
    Volt::route('/children', 'parents.children.index')
        ->name('children.index');

    Volt::route('/children/create', 'parents.children.create')
        ->name('children.create');

    Volt::route('/children/{child}', 'parents.children.show')
        ->name('children.show');

    Volt::route('/children/{child}/edit', 'parents.children.edit')
        ->name('children.edit');

    // Calendar and Schedule Management
    Volt::route('/calendar', 'parents.calendar')
        ->name('calendar');

    Volt::route('/schedule', 'parents.schedule.index')
        ->name('schedule.index');

    // Learning Progress and Reports
    Volt::route('/progress', 'parents.progress.index')
        ->name('progress.index');

    Volt::route('/progress/{child}', 'parents.progress.child')
        ->name('progress.child');

    Volt::route('/reports', 'parents.reports.index')
        ->name('reports.index');

    Volt::route('/reports/{child}', 'parents.reports.child')
        ->name('reports.child');

    // Sessions and Lessons
    Volt::route('/sessions', 'parents.sessions.index')
        ->name('sessions.index');

    Volt::route('/sessions/{session}', 'parents.sessions.show')
        ->name('sessions.show');

    Volt::route('/session-requests', 'parents.sessions.requests')
        ->name('sessions.requests');

    // Assessments and Homework
    Volt::route('/assessments', 'parents.assessments.index')
        ->name('assessments.index');

    Volt::route('/assessments/{assessment}', 'parents.assessments.show')
        ->name('assessments.show');

    Volt::route('/homework', 'parents.homework.index')
        ->name('homework.index');

    Volt::route('/homework/{homework}', 'parents.homework.show')
        ->name('homework.show');

    // Learning Materials
    Volt::route('/materials', 'parents.materials.index')
        ->name('materials.index');

    Volt::route('/materials/{material}', 'parents.materials.show')
        ->name('materials.show');

    // Billing and Payments
    Volt::route('/billing', 'parents.billing.index')
        ->name('billing.index');

    Volt::route('/payments', 'parents.payments.index')
        ->name('payments.index');

    Volt::route('/invoices', 'parents.invoices.index')
        ->name('invoices.index');

    Volt::route('/invoices/{invoice}', 'parents.invoices.show')
        ->name('invoices.show');

    // Communications
    Volt::route('/messages', 'parents.messages.index')
        ->name('messages.index');

    Volt::route('/messages/{conversation}', 'parents.messages.show')
        ->name('messages.show');

    Volt::route('/notifications', 'parents.notifications.index')
        ->name('notifications.index');

    // Support and Help
    Volt::route('/support', 'parents.support.index')
        ->name('support.index');

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
