<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormRequestController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 公告中心
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');

    // 表單簽核工作流
    Route::get('/forms', [FormRequestController::class, 'index'])->name('forms.index');
    Route::get('/forms/create/{form}', [FormRequestController::class, 'create'])->name('forms.create');
    Route::post('/forms/create/{form}', [FormRequestController::class, 'store'])->name('forms.store');
    Route::get('/forms/requests/{formRequest}', [FormRequestController::class, 'show'])->name('forms.show');
    Route::post('/forms/requests/{formRequest}/action', [FormRequestController::class, 'action'])->name('forms.action');

    // 組織通訊錄
    Route::get('/directory', [OrganizationController::class, 'index'])->name('directory.index');

    // 考勤打卡
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');

    // 會議室借用與行事曆
    Route::get('/meeting-rooms', [MeetingRoomController::class, 'index'])->name('meeting-rooms.index');
    Route::post('/meeting-rooms', [MeetingRoomController::class, 'storeRoom'])->name('meeting-rooms.store');
    Route::put('/meeting-rooms/{meetingRoom}', [MeetingRoomController::class, 'updateRoom'])->name('meeting-rooms.update');
    Route::post('/meeting-rooms/bookings', [MeetingRoomController::class, 'storeBooking'])->name('meeting-rooms.bookings.store');
    Route::post('/meeting-rooms/bookings/{booking}/cancel', [MeetingRoomController::class, 'cancelBooking'])->name('meeting-rooms.bookings.cancel');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
