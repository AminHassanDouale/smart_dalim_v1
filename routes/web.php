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

   // Volt::route('/parents/profile-setup', 'parents.profile-setup.steps')
   // ->name('parents.profile-setup');
//Volt::route('/parents/profile', 'parents.profile')->name('parents.profile');
   // Volt::route('parents/steps', 'parents.steps')->name('parents.steps');
   // Volt::route('/parents/profile-setup', 'parents.steps')
   // ->name('parents.profile-setup');
//
//
   // // Other Parent Routes
   // Volt::route('/parents/profile', 'parents.profile')
   //     ->name('parents.profile');
    //Volt::route('/parents/dashboard', 'parents.dashboard')
    //    ->name('parents.dashboard');

    Volt::route('/parents/profile-setup', 'parents.profile-setup.steps')
    ->name('parents.profile-setup');


Volt::route('/parents/profile', 'parents.profile')
    ->name('parents.profile');

// Add the dashboard route
Volt::route('/parents/dashboard', 'parents.dashboard')
    ->name('parents.dashboard');
});

// Teacher Routes
Route::middleware(['auth', 'role:teacher'])->group(function () {
    // Profile Setup Route
    Volt::route('/teachers/profile-setup', 'teachers.profile-setup')
        ->name('teachers.profile-setup');
        Volt::route('/teachers/{user}/edit', 'teachers.edit');



    // Other Teacher Routes
    Volt::route('/teachers/profile', 'teachers.profile')
        ->name('teachers.profile');
    Volt::route('/teachers/dashboard', 'teachers.dashboard')
        ->name('teachers.dashboard');
    Volt::route('/teachers/{teacher}/timetable', 'teachers.timetable')
        ->name('teachers.timetable');
    Volt::route('/teachers/{teacher}/class', 'teachers.class')
        ->name('teachers.class');
});

//quran
// Quran Routes
Route::middleware(['auth'])->prefix('recites')->name('recites.')->group(function () {
    Volt::route('/index', 'recites.index')
        ->name('index');

    Volt::route('/chapter/{id}', 'recites.chapter')
        ->name('chapter');

    // Page-specific routes
    Volt::route('/page/{page}', 'recites.verse-by-page')
        ->name('page')
        ->where('page', '[1-9][0-9]*');

    // Chapter-specific page routes
    Volt::route('/chapter/{chapter}/page/{page}', 'recites.verse-by-page')
        ->name('chapter.page')
        ->where(['chapter' => '[1-9][0-9]*', 'page' => '[1-9][0-9]*']);
});

// Store Routes (if needed)
Route::middleware('auth')->group(function () {
    Volt::route('video-call/{roomId?}', 'video-call')
    ->name('video-call');
    Volt::route('/cart', 'store.cart.index')->name('cart');
    Volt::route('/wishlist', 'store.wishlist.index')->name('wishlist');
    Volt::route('/checkout', 'store.checkout.index')->name('checkout');
    Volt::route('/orders', 'store.orders.index')->name('orders');
    Volt::route('/orders/{order}', 'store.orders.show')->name('orders.show');
    Volt::route('/home', 'store.index')->name('home');
    Volt::route('/products/{product}', 'store.products.show')->name('products.show');
    Volt::route('/support-us', 'support-us')->name('support');
    Volt::route('/test-broadcast', 'test-broadcast')
    ->name('test-broadcast');
});
