<?php

use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Hr\ApprovalController;
use App\Http\Controllers\Hr\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route(auth()->user()->role->value.'.home'))->name('home');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    foreach (['employee'] as $role) {
        Route::view('/'.$role, 'foundation', ['role' => $role])->middleware('role:'.$role)->name($role.'.home');
    }
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('home');
    Route::get('/employees', [MonitoringController::class, 'employees'])->name('employees.index');
    Route::get('/attendance', [MonitoringController::class, 'attendance'])->name('attendance.index');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});

Route::prefix('hr')->name('hr.')->middleware(['auth', 'role:hr'])->group(function () {
    Route::get('/', [App\Http\Controllers\Hr\MonitoringController::class, 'dashboard'])->name('home');
    Route::resource('employees', EmployeeController::class)->except(['show', 'destroy']);
    Route::get('/attendance/export', [App\Http\Controllers\Hr\MonitoringController::class, 'export'])->name('attendance.export');
    Route::get('/attendance', [App\Http\Controllers\Hr\MonitoringController::class, 'attendance'])->name('attendance.index');
    Route::get('/enrollment', [App\Http\Controllers\Hr\MonitoringController::class, 'enrollment'])->name('enrollment');
    Route::get('/corrections', [ApprovalController::class, 'corrections'])->name('corrections.index');
    Route::get('/corrections/{correction}', [ApprovalController::class, 'correction'])->name('corrections.show');
    Route::post('/corrections/{correction}/decision', [ApprovalController::class, 'decideCorrection'])->name('corrections.decision');
    Route::get('/corrections/{correction}/evidence', [ApprovalController::class, 'evidence'])->name('corrections.evidence');
    Route::get('/leaves', [ApprovalController::class, 'leaves'])->name('leaves.index');
    Route::get('/leaves/{leave}', [ApprovalController::class, 'leave'])->name('leaves.show');
    Route::post('/leaves/{leave}/decision', [ApprovalController::class, 'decideLeave'])->name('leaves.decision');
    Route::get('/leaves/{leave}/attachment', [ApprovalController::class, 'attachment'])->name('leaves.attachment');
});
