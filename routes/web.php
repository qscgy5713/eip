<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FormRequestController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebhookController;
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
    Route::post('/forms/templates', [FormRequestController::class, 'storeTemplate'])->name('forms.templates.store');
    Route::delete('/forms/templates/{form}', [FormRequestController::class, 'destroyTemplate'])->name('forms.templates.destroy');
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

    // 企業文件庫與檔案版本控制
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'uploadVersion'])->name('documents.versions.upload');
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions.list');
    Route::get('/documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // 站內通知中心
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // 系統審計稽核日誌 (管理員專屬)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // 外部生態 Webhook 整合 (管理員專屬)
    Route::get('/webhooks', [WebhookController::class, 'index'])->name('webhooks.index');
    Route::post('/webhooks', [WebhookController::class, 'store'])->name('webhooks.store');
    Route::post('/webhooks/{webhook}/ping', [WebhookController::class, 'ping'])->name('webhooks.ping');
    Route::patch('/webhooks/{webhook}/toggle', [WebhookController::class, 'toggle'])->name('webhooks.toggle');
    Route::delete('/webhooks/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');

    // 簽核職務代理人設定
    Route::get('/delegations', [DelegationController::class, 'index'])->name('delegations.index');
    Route::post('/delegations', [DelegationController::class, 'store'])->name('delegations.store');
    Route::patch('/delegations/{delegation}/toggle', [DelegationController::class, 'toggle'])->name('delegations.toggle');
    Route::delete('/delegations/{delegation}', [DelegationController::class, 'destroy'])->name('delegations.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
