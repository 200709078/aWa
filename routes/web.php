<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Home'));
    Route::get('/students', fn () => Inertia::render('Students/Index'));
    Route::get('/branches', fn () => Inertia::render('Branches/Index'));
    Route::get('/rooms', fn () => Inertia::render('Rooms/Index'));
    Route::get('/exam-weeks', fn () => Inertia::render('ExamWeeks/Index'));
    Route::get('/distribution', fn () => Inertia::render('Distribution/Index'));
    Route::get('/reports', fn () => Inertia::render('Reports/Index'));
    Route::get('/settings', fn () => Inertia::render('Settings/Index'));

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
