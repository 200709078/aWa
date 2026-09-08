<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamWeekController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentPhotoController;
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

    Route::get('/students/import', [StudentImportController::class, 'show'])->name('students.import');
    Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('/students/import/confirm', [StudentImportController::class, 'confirm'])->name('students.import.confirm');

    Route::get('/students/photos', [StudentPhotoController::class, 'show'])->name('students.photos');
    Route::post('/students/photos', [StudentPhotoController::class, 'store'])->name('students.photos.store');

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
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::post('/rooms/{room}/activate', [RoomController::class, 'activate'])->name('rooms.activate');
    Route::post('/rooms/{room}/deactivate', [RoomController::class, 'deactivate'])->name('rooms.deactivate');
    Route::post('/rooms/{room}/seats', [SeatController::class, 'store'])->name('seats.store');
    Route::post('/rooms/{room}/seats/bulk', [SeatController::class, 'bulk'])->name('seats.bulk');
    Route::post('/seats/{seat}/toggle', [SeatController::class, 'toggle'])->name('seats.toggle');
    Route::get('/exam-weeks', [ExamWeekController::class, 'index'])->name('exam-weeks.index');
    Route::post('/exam-weeks', [ExamWeekController::class, 'store'])->name('exam-weeks.store');
    Route::get('/exam-weeks/{examWeek}', [ExamWeekController::class, 'show'])->name('exam-weeks.show');
    Route::put('/exam-weeks/{examWeek}', [ExamWeekController::class, 'update'])->name('exam-weeks.update');
    Route::post('/exam-weeks/{examWeek}/activate', [ExamWeekController::class, 'activate'])->name('exam-weeks.activate');
    Route::post('/exam-weeks/{examWeek}/deactivate', [ExamWeekController::class, 'deactivate'])->name('exam-weeks.deactivate');
    Route::put('/exam-weeks/{examWeek}/branches', [ExamWeekController::class, 'syncBranches'])->name('exam-weeks.branches');
    Route::put('/exam-weeks/{examWeek}/rooms', [ExamWeekController::class, 'syncRooms'])->name('exam-weeks.rooms');
    Route::post('/exam-weeks/{examWeek}/exams', [ExamController::class, 'store'])->name('exams.store');
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
    Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');
    Route::post('/distribution', [DistributionController::class, 'store'])->name('distribution.store');
    Route::get('/distribution/plans/{plan}', [DistributionController::class, 'show'])->name('distribution.plans.show');
    Route::post('/distribution/plans/{plan}/finalize', [DistributionController::class, 'finalize'])->name('distribution.plans.finalize');
    Route::post('/distribution/plans/{plan}/reopen', [DistributionController::class, 'reopen'])->name('distribution.plans.reopen');
    Route::post('/distribution/plans/{plan}/move', [DistributionController::class, 'move'])->name('distribution.plans.move');
    Route::post('/distribution/plans/{plan}/swap', [DistributionController::class, 'swap'])->name('distribution.plans.swap');
    Route::get('/distribution/plans/{plan}/print/seating', [PrintController::class, 'seating'])->name('distribution.print.seating');
    Route::get('/distribution/plans/{plan}/print/branches', [PrintController::class, 'branches'])->name('distribution.print.branches');
    Route::get('/distribution/plans/{plan}/print/rooms', [PrintController::class, 'rooms'])->name('distribution.print.rooms');
    Route::get('/distribution/plans/{plan}/print/summary', [PrintController::class, 'summary'])->name('distribution.print.summary');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
