<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentImportController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceRekapController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobExportController;
use App\Http\Controllers\JobImportController;
use App\Http\Controllers\JobPeriodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserExportController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/export', [UserExportController::class, 'download'])->name('export');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    Route::get('/{user}/reset-password', [UserController::class, 'editPassword'])->name('reset-password.edit');
    Route::put('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password.update');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
});

Route::middleware('auth')->prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/import', [EmployeeImportController::class, 'create'])->name('import.create');
        Route::post('/import', [EmployeeImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [EmployeeImportController::class, 'downloadErrors'])->name('import.errors');
        Route::get('/export', [EmployeeExportController::class, 'download'])->name('export');
    });

    Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show')->whereNumber('employee');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
    });
});

Route::middleware('auth')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [JobController::class, 'create'])->name('create');
        Route::post('/', [JobController::class, 'store'])->name('store');
        Route::get('/import', [JobImportController::class, 'create'])->name('import.create');
        Route::post('/import', [JobImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [JobImportController::class, 'downloadErrors'])->name('import.errors');
        Route::get('/export', [JobExportController::class, 'download'])->name('export');
    });

    Route::get('/{job}', [JobController::class, 'show'])->name('show')->whereNumber('job');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::patch('/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{job}/renew', [JobController::class, 'renew'])->name('renew');
        Route::get('/{job}/periods/create', [JobPeriodController::class, 'create'])->name('periods.create');
        Route::post('/{job}/periods', [JobPeriodController::class, 'store'])->name('periods.store');
    });
});

Route::middleware('auth')->prefix('job-periods')->name('job-periods.')->group(function () {
    Route::get('/{jobPeriod}', [JobPeriodController::class, 'show'])->name('show')->whereNumber('jobPeriod');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/{jobPeriod}/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/{jobPeriod}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    });
});

Route::middleware(['auth', 'role:admin|staff_input'])->prefix('assignments')->name('assignments.')->group(function () {
    Route::get('/import', [AssignmentImportController::class, 'create'])->name('import.create');
    Route::post('/import', [AssignmentImportController::class, 'store'])->name('import.store');
    Route::get('/import/errors', [AssignmentImportController::class, 'downloadErrors'])->name('import.errors');

    Route::get('/{assignment}/edit', [AssignmentController::class, 'edit'])->name('edit')->whereNumber('assignment');
    Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('update')->whereNumber('assignment');
    Route::get('/{assignment}/end', [AssignmentController::class, 'endForm'])->name('end.form')->whereNumber('assignment');
    Route::patch('/{assignment}/end', [AssignmentController::class, 'end'])->name('end')->whereNumber('assignment');
});

Route::middleware('auth')->prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/rekap', [AttendanceRekapController::class, 'index'])->name('rekap');
    Route::get('/rekap/export', [AttendanceRekapController::class, 'export'])->name('rekap.export');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/input', [AttendanceController::class, 'input'])->name('input');
        Route::post('/input', [AttendanceController::class, 'store'])->name('input.store');
    });
});

require __DIR__.'/auth.php';
