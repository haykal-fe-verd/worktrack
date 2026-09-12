<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeExportController;
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobExportController;
use App\Http\Controllers\JobImportController;
use App\Http\Controllers\JobPeriodController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');

        Route::get('/import', [EmployeeImportController::class, 'create'])->name('import.create');
        Route::post('/import', [EmployeeImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [EmployeeImportController::class, 'downloadErrors'])->name('import.errors');
        Route::get('/export', [EmployeeExportController::class, 'download'])->name('export');
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

    Route::get('/{job}', [JobController::class, 'show'])->name('show');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::patch('/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{job}/renew', [JobController::class, 'renew'])->name('renew');
        Route::get('/{job}/periods/create', [JobPeriodController::class, 'create'])->name('periods.create');
        Route::post('/{job}/periods', [JobPeriodController::class, 'store'])->name('periods.store');
    });
});

require __DIR__.'/auth.php';
