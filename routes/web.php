<?php

use App\Http\Controllers\Admin\MonitoringController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Employee\PortalController;
use App\Http\Controllers\Hr\ApprovalController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\Hr\FaceAttendanceController;
use App\Http\Controllers\Hr\FaceEnrollmentController;
use App\Http\Controllers\Hr\KioskDeviceController;
use App\Http\Controllers\Kiosk\ActivationController;
use App\Http\Controllers\Kiosk\AttendanceController as KioskAttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/activate', [ActivationController::class, 'show'])->name('activate');
    Route::post('/activate', [ActivationController::class, 'store'])->middleware('throttle:5,1')->name('activate.store');
    Route::middleware('kiosk.device')->group(function () {
        Route::get('/', [KioskAttendanceController::class, 'show'])->name('home');
        Route::get('/status', [KioskAttendanceController::class, 'status'])->middleware('throttle:30,1')->name('status');
        Route::post('/start', [KioskAttendanceController::class, 'start'])->middleware('throttle:5,1')->name('start');
        Route::post('/stop', [KioskAttendanceController::class, 'stop'])->name('stop');
        Route::post('/scan', [KioskAttendanceController::class, 'store'])->middleware('throttle:10,1')->name('scan');
    });
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});
Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route(auth()->user()->role->value.'.home'))->name('home');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('home');
    Route::get('/employees', [MonitoringController::class, 'employees'])->middleware('can:viewAny,'.\App\Models\Employee::class)->name('employees.index');
    Route::get('/employees/{employee}', [MonitoringController::class, 'employee'])->middleware('can:view,employee')->name('employees.show');
    Route::get('/attendance', [MonitoringController::class, 'attendance'])->name('attendance.index');
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});

Route::prefix('hr')->name('hr.')->middleware(['auth', 'role:hr'])->group(function () {
    Route::get('/kiosk/devices', [KioskDeviceController::class, 'index'])->name('kiosk-devices.index');
    Route::post('/kiosk/devices', [KioskDeviceController::class, 'store'])->middleware('throttle:5,1')->name('kiosk-devices.store');
    Route::post('/kiosk/devices/{device}/revoke', [KioskDeviceController::class, 'revoke'])->name('kiosk-devices.revoke');
    Route::get('/kiosk', [FaceAttendanceController::class, 'show'])->name('kiosk');
    Route::get('/kiosk/status', [FaceAttendanceController::class, 'status'])->middleware('throttle:30,1')->name('kiosk.status');
    Route::post('/kiosk/start', [FaceAttendanceController::class, 'start'])->middleware('throttle:5,1')->name('kiosk.start');
    Route::post('/kiosk/stop', [FaceAttendanceController::class, 'stop'])->name('kiosk.stop');
    Route::post('/kiosk/scan', [FaceAttendanceController::class, 'store'])->middleware('throttle:10,1')->name('kiosk.scan');
    Route::get('/', [App\Http\Controllers\Hr\MonitoringController::class, 'dashboard'])->name('home');
    Route::resource('employees', EmployeeController::class)->except(['destroy']);
    Route::get('/employees/{employee}/face', [FaceEnrollmentController::class, 'show'])->name('faces.show');
    Route::post('/employees/{employee}/face', [FaceEnrollmentController::class, 'store'])->middleware('throttle:3,1')->name('faces.store');
    Route::delete('/employees/{employee}/face', [FaceEnrollmentController::class, 'destroy'])->middleware('throttle:5,1')->name('faces.destroy');
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

Route::prefix('employee')->name('employee.')->middleware(['auth', 'role:employee'])->group(function () {
    Route::get('/', [PortalController::class, 'home'])->name('home');
    Route::get('/attendance', [PortalController::class, 'attendance'])->name('attendance.index');
    Route::get('/corrections', [PortalController::class, 'corrections'])->name('corrections.index');
    Route::get('/corrections/create', [PortalController::class, 'createCorrection'])->name('corrections.create');
    Route::post('/corrections', [PortalController::class, 'storeCorrection'])->middleware('throttle:10,1')->name('corrections.store');
    Route::get('/corrections/{correction}', [PortalController::class, 'correction'])->name('corrections.show');
    Route::get('/corrections/{correction}/evidence', [PortalController::class, 'evidence'])->name('corrections.evidence');
    Route::get('/leaves', [PortalController::class, 'leaves'])->name('leaves.index');
    Route::get('/leaves/create', [PortalController::class, 'createLeave'])->name('leaves.create');
    Route::post('/leaves', [PortalController::class, 'storeLeave'])->middleware('throttle:10,1')->name('leaves.store');
    Route::get('/leaves/{leave}', [PortalController::class, 'leave'])->name('leaves.show');
    Route::get('/leaves/{leave}/attachment', [PortalController::class, 'attachment'])->name('leaves.attachment');
});

Route::get('/invitation/{token}', [InvitationController::class, 'show'])->middleware(['guest', 'throttle:30,1'])->name('invitation.show');
Route::post('/invitation', [InvitationController::class, 'store'])->middleware(['guest', 'throttle:10,1'])->name('invitation.activate');
Route::post('/admin/users/{user}/invitation', [UserController::class, 'resend'])->middleware(['auth', 'role:admin', 'throttle:5,1'])->name('admin.users.invitation');
