<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\StudentController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Home'));

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/activate', [StudentController::class, 'activate'])->name('students.activate');
    Route::post('/students/{student}/deactivate', [StudentController::class, 'deactivate'])->name('students.deactivate');

    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
    Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
    Route::post('/academic-years/{academicYear}/deactivate', [AcademicYearController::class, 'deactivate'])->name('academic-years.deactivate');

    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::post('/branches/{branch}/activate', [BranchController::class, 'activate'])->name('branches.activate');
    Route::post('/branches/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('branches.deactivate');
    Route::get('/rooms', fn () => Inertia::render('Rooms/Index'));
    Route::get('/exam-weeks', fn () => Inertia::render('ExamWeeks/Index'));
    Route::get('/distribution', fn () => Inertia::render('Distribution/Index'));
    Route::get('/reports', fn () => Inertia::render('Reports/Index'));
    Route::get('/settings', fn () => Inertia::render('Settings/Index'));

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
