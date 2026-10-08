<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApprovalHubController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FormRequestController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgManagementController;
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
    Route::get('/forms/requests/{formRequest}/print', [FormRequestController::class, 'print'])->name('forms.print');
    Route::get('/forms/requests/{formRequest}/attachments/{index}', [FormRequestController::class, 'downloadAttachment'])->name('forms.attachments.download');
    Route::post('/forms/requests/{formRequest}/action', [FormRequestController::class, 'action'])->name('forms.action');
    Route::post('/forms/requests/{formRequest}/transfer', [FormRequestController::class, 'transfer'])->name('forms.transfer');
    Route::post('/forms/requests/{formRequest}/add-sign', [FormRequestController::class, 'addSign'])->name('forms.add-sign');

    // 主管審批中心與一鍵批次簽核
    Route::get('/approvals', [ApprovalHubController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/batch-action', [ApprovalHubController::class, 'batchAction'])->name('approvals.batchAction');

    // 組織通訊錄
    Route::get('/directory', [OrganizationController::class, 'index'])->name('directory.index');

    // 考勤打卡與月報結算
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clockIn');
    Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clockOut');
    Route::get('/attendance/reports', [AttendanceReportController::class, 'index'])->name('attendance.reports.index');
    Route::get('/attendance/reports/export-summary', [AttendanceReportController::class, 'exportSummary'])->name('attendance.reports.exportSummary');
    Route::get('/attendance/reports/export-details', [AttendanceReportController::class, 'exportDetails'])->name('attendance.reports.exportDetails');
    Route::get('/attendance/settings', [AttendanceController::class, 'settings'])->name('attendance.settings');
    Route::post('/attendance/settings', [AttendanceController::class, 'updateSettings'])->name('attendance.settings.update');

    // 特休與休假額度管理
    Route::get('/leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::put('/leave-balances/{user}', [LeaveBalanceController::class, 'update'])->name('leave-balances.update');
    Route::post('/leave-balances/batch-init', [LeaveBalanceController::class, 'batchInit'])->name('leave-balances.batchInit');

    // 會議室借用與行事曆
    Route::get('/meeting-rooms', [MeetingRoomController::class, 'index'])->name('meeting-rooms.index');
    Route::post('/meeting-rooms', [MeetingRoomController::class, 'storeRoom'])->name('meeting-rooms.store');
    Route::put('/meeting-rooms/{meetingRoom}', [MeetingRoomController::class, 'updateRoom'])->name('meeting-rooms.update');
    Route::post('/meeting-rooms/bookings', [MeetingRoomController::class, 'storeBooking'])->name('meeting-rooms.bookings.store');
    Route::post('/meeting-rooms/bookings/{booking}/cancel', [MeetingRoomController::class, 'cancelBooking'])->name('meeting-rooms.bookings.cancel');

    // 企業綜合行事曆看板
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');

    // 企業文件庫與檔案版本控制
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'uploadVersion'])->name('documents.versions.upload');
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions.list');
    Route::get('/documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview/{version?}', [DocumentController::class, 'preview'])->name('documents.preview');
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

    // 組織架構與人員管理後台 (限 Admin 與 HR)
    Route::get('/org-management', [OrgManagementController::class, 'index'])->name('org-management.index');
    Route::get('/org-management/export-roster', [OrgManagementController::class, 'exportRoster'])->name('org-management.export-roster');
    Route::post('/org-management/departments', [OrgManagementController::class, 'storeDepartment'])->name('org-management.departments.store');
    Route::put('/org-management/departments/{department}', [OrgManagementController::class, 'updateDepartment'])->name('org-management.departments.update');
    Route::patch('/org-management/departments/{department}/move', [OrgManagementController::class, 'moveDepartment'])->name('org-management.departments.move');
    Route::post('/org-management/departments/{department}/leader', [OrgManagementController::class, 'setLeader'])->name('org-management.departments.leader');
    Route::post('/org-management/departments/{department}/members', [OrgManagementController::class, 'addMember'])->name('org-management.departments.members.add');
    Route::delete('/org-management/departments/{department}/members/{user}', [OrgManagementController::class, 'removeMember'])->name('org-management.departments.members.remove');
    Route::delete('/org-management/departments/{department}', [OrgManagementController::class, 'destroyDepartment'])->name('org-management.departments.destroy');
    Route::post('/org-management/users', [OrgManagementController::class, 'storeUser'])->name('org-management.users.store');
    Route::put('/org-management/users/{user}', [OrgManagementController::class, 'updateUser'])->name('org-management.users.update');
    Route::post('/org-management/users/{user}/reset-password', [OrgManagementController::class, 'resetPassword'])->name('org-management.users.reset-password');
    Route::post('/org-management/users/{user}/status', [OrgManagementController::class, 'updateUserStatus'])->name('org-management.users.status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
