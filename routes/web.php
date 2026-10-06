<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BilgiFormuController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamWeekController;
use App\Http\Controllers\GraduateController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\RehberAktarmaController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\SeatController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentPhotoController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::get('/select-school', [LoginController::class, 'showSchool'])->name('schools.select');
Route::post('/select-school', [LoginController::class, 'storeSchool'])->name('schools.select.store');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});

Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/schools', [SchoolController::class, 'index'])->name('schools.index');
    Route::post('/schools', [SchoolController::class, 'store'])->name('schools.store');
    Route::put('/schools/{school}', [SchoolController::class, 'update'])->name('schools.update');
    Route::delete('/schools/{school}', [SchoolController::class, 'destroy'])->name('schools.destroy');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

Route::middleware(['auth', 'school'])->group(function () {
    Route::get('/', fn () => Inertia::render('Home'));

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/ekle', [StudentController::class, 'create'])->name('students.create');
    Route::get('/students/{student}/duzenle', [StudentController::class, 'edit'])->name('students.edit');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/students/{student}/activate', [StudentController::class, 'activate'])->name('students.activate');
    Route::post('/students/{student}/deactivate', [StudentController::class, 'deactivate'])->name('students.deactivate');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/students/import', [StudentImportController::class, 'show'])->name('students.import');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('/students/import/confirm', [StudentImportController::class, 'confirm'])->name('students.import.confirm');

    Route::get('/students/photos', [StudentPhotoController::class, 'show'])->name('students.photos');
    Route::post('/students/photos/match', [StudentPhotoController::class, 'match'])->name('students.photos.match');
    Route::post('/students/photos/confirm', [StudentPhotoController::class, 'confirm'])->name('students.photos.confirm');

    Route::get('/rehber-aktarma', [RehberAktarmaController::class, 'index'])->name('rehber.index');
    Route::get('/rehber-aktarma/yillar/{academicYear}/subeler', [RehberAktarmaController::class, 'branches'])->name('rehber.branches');
    Route::post('/rehber-aktarma/ozet', [RehberAktarmaController::class, 'summary'])->name('rehber.summary');
    Route::get('/rehber-aktarma/indir', [RehberAktarmaController::class, 'download'])->name('rehber.download');
    Route::get('/rehber-aktarma/excel', [RehberAktarmaController::class, 'excel'])->name('rehber.excel');

    Route::get('/mezunlar', [GraduateController::class, 'index'])->name('mezunlar.index');    Route::get('/mezunlar/ekle', [GraduateController::class, 'create'])->name('mezunlar.create');
    Route::get('/mezunlar/{graduate}/duzenle', [GraduateController::class, 'edit'])->name('mezunlar.edit');
    Route::post('/mezunlar', [GraduateController::class, 'store'])->name('mezunlar.store');
    Route::put('/mezunlar/{graduate}', [GraduateController::class, 'update'])->name('mezunlar.update');
    Route::delete('/mezunlar/{graduate}', [GraduateController::class, 'destroy'])->name('mezunlar.destroy');
    Route::get('/mezunlar/vcf', [GraduateController::class, 'vcf'])->name('mezunlar.vcf');

    Route::get('/bilgi-formlari', [BilgiFormuController::class, 'index'])->name('bilgi-formlari.index');
    Route::get('/bilgi-formlari/ice-aktar', [BilgiFormuController::class, 'importShow'])->name('bilgi-formlari.import');
    Route::post('/bilgi-formlari/ice-aktar', [BilgiFormuController::class, 'importStore'])->name('bilgi-formlari.import.store');
    Route::get('/bilgi-formlari/yazdir', [BilgiFormuController::class, 'print'])->name('bilgi-formlari.print');
    Route::get('/bilgi-formlari/risk-haritasi', [BilgiFormuController::class, 'riskMap'])->name('bilgi-formlari.risk');
    Route::get('/bilgi-formlari/{student}/duzenle', [BilgiFormuController::class, 'edit'])->name('bilgi-formlari.edit');
    Route::put('/bilgi-formlari/{student}', [BilgiFormuController::class, 'update'])->name('bilgi-formlari.update');

    Route::get('/personel', [TeacherController::class, 'index'])->name('personel.index');
    Route::get('/personel/ekle', [TeacherController::class, 'create'])->name('personel.create');
    Route::get('/personel/{teacher}/duzenle', [TeacherController::class, 'edit'])->name('personel.edit');
    Route::get('/personel/yaka-kartlari', [TeacherController::class, 'badges'])->name('personel.badges');
    Route::post('/personel', [TeacherController::class, 'store'])->name('personel.store');
    Route::put('/personel/{teacher}', [TeacherController::class, 'update'])->name('personel.update');
    Route::post('/personel/{teacher}/activate', [TeacherController::class, 'activate'])->name('personel.activate');
    Route::post('/personel/{teacher}/deactivate', [TeacherController::class, 'deactivate'])->name('personel.deactivate');
    Route::delete('/personel/{teacher}', [TeacherController::class, 'destroy'])->name('personel.destroy');

    Route::get('/arsiv', [ArchiveController::class, 'index'])->name('archive.index');
    Route::post('/arsiv/ogrenciler/{id}/restore', [ArchiveController::class, 'restoreStudent'])->name('archive.students.restore');
    Route::delete('/arsiv/ogrenciler/{id}', [ArchiveController::class, 'forceStudent'])->name('archive.students.force');
    Route::post('/arsiv/personel/{id}/restore', [ArchiveController::class, 'restoreTeacher'])->name('archive.teachers.restore');
    Route::delete('/arsiv/personel/{id}', [ArchiveController::class, 'forceTeacher'])->name('archive.teachers.force');
    Route::post('/arsiv/mezunlar/{id}/restore', [ArchiveController::class, 'restoreGraduate'])->name('archive.graduates.restore');
    Route::delete('/arsiv/mezunlar/{id}', [ArchiveController::class, 'forceGraduate'])->name('archive.graduates.force');

    Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
    Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update'])->name('academic-years.update');
    Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
    Route::post('/academic-years/{academicYear}/deactivate', [AcademicYearController::class, 'deactivate'])->name('academic-years.deactivate');
    Route::delete('/academic-years/{academicYear}', [AcademicYearController::class, 'destroy'])->name('academic-years.destroy');

    Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    Route::post('/branches/{branch}/activate', [BranchController::class, 'activate'])->name('branches.activate');
    Route::post('/branches/{branch}/deactivate', [BranchController::class, 'deactivate'])->name('branches.deactivate');
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::post('/rooms/{room}/activate', [RoomController::class, 'activate'])->name('rooms.activate');
    Route::post('/rooms/{room}/deactivate', [RoomController::class, 'deactivate'])->name('rooms.deactivate');
    Route::post('/rooms/{room}/seats', [SeatController::class, 'store'])->name('seats.store');
    Route::post('/rooms/{room}/seats/bulk', [SeatController::class, 'bulk'])->name('seats.bulk');
    Route::post('/seats/{seat}/toggle', [SeatController::class, 'toggle'])->name('seats.toggle');
    Route::delete('/seats/{seat}', [SeatController::class, 'destroy'])->name('seats.destroy');
    Route::get('/exam-weeks', [ExamWeekController::class, 'index'])->name('exam-weeks.index');
    Route::post('/exam-weeks', [ExamWeekController::class, 'store'])->name('exam-weeks.store');
    Route::get('/exam-weeks/{examWeek}', [ExamWeekController::class, 'show'])->name('exam-weeks.show');
    Route::put('/exam-weeks/{examWeek}', [ExamWeekController::class, 'update'])->name('exam-weeks.update');
    Route::delete('/exam-weeks/{examWeek}', [ExamWeekController::class, 'destroy'])->name('exam-weeks.destroy');
    Route::post('/exam-weeks/{examWeek}/activate', [ExamWeekController::class, 'activate'])->name('exam-weeks.activate');
    Route::post('/exam-weeks/{examWeek}/deactivate', [ExamWeekController::class, 'deactivate'])->name('exam-weeks.deactivate');
    Route::put('/exam-weeks/{examWeek}/branches', [ExamWeekController::class, 'syncBranches'])->name('exam-weeks.branches');
    Route::put('/exam-weeks/{examWeek}/rooms', [ExamWeekController::class, 'syncRooms'])->name('exam-weeks.rooms');
    Route::post('/exam-weeks/{examWeek}/exams', [ExamController::class, 'store'])->name('exams.store');
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
    Route::get('/distribution', [DistributionController::class, 'index'])->name('distribution.index');
    Route::post('/distribution', [DistributionController::class, 'store'])->name('distribution.store');
    Route::get('/distribution/plans/{plan}', [DistributionController::class, 'show'])->name('distribution.plans.show');
    Route::delete('/distribution/plans/{plan}', [DistributionController::class, 'destroy'])->name('distribution.plans.destroy');
    Route::post('/distribution/plans/{plan}/finalize', [DistributionController::class, 'finalize'])->name('distribution.plans.finalize');
    Route::post('/distribution/plans/{plan}/reopen', [DistributionController::class, 'reopen'])->name('distribution.plans.reopen');
    Route::post('/distribution/plans/{plan}/move', [DistributionController::class, 'move'])->name('distribution.plans.move');
    Route::post('/distribution/plans/{plan}/swap', [DistributionController::class, 'swap'])->name('distribution.plans.swap');
    Route::get('/distribution/plans/{plan}/print/seating', [PrintController::class, 'seating'])->name('distribution.print.seating');
    Route::get('/distribution/plans/{plan}/print/branches', [PrintController::class, 'branches'])->name('distribution.print.branches');
    Route::get('/distribution/plans/{plan}/print/rooms', [PrintController::class, 'rooms'])->name('distribution.print.rooms');
    Route::get('/distribution/plans/{plan}/print/summary', [PrintController::class, 'summary'])->name('distribution.print.summary');
});
