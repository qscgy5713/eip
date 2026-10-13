<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ApprovalHubController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegationController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\FormRequestController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\MealOrderController;
use App\Http\Controllers\MeetingRoomController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgManagementController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VehicleDispatchController;
use App\Http\Controllers\VisitorReservationController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\WeeklyReportController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// 品牌 Logo / Favicon 檔案 (登入頁也會用到,不需登入)
Route::get('/branding/{kind}', [\App\Http\Controllers\BrandingController::class, 'asset'])->where('kind', 'logo|favicon')->name('branding.asset');

Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/global-search', [GlobalSearchController::class, 'search'])->name('global-search');

    // 公告中心
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('/announcements/{announcement}/attachments/{index}', [AnnouncementController::class, 'downloadAttachment'])->name('announcements.attachments.download');

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
    Route::post('/forms/requests/{formRequest}/withdraw', [FormRequestController::class, 'withdraw'])->name('forms.withdraw');
    Route::post('/forms/requests/{formRequest}/resubmit', [FormRequestController::class, 'resubmit'])->name('forms.requests.resubmit');

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
    Route::post('/announcements/{announcement}/remind', [\App\Http\Controllers\AnnouncementController::class, 'remind'])->name('announcements.remind');
    Route::get('/system-health', [\App\Http\Controllers\SystemHealthController::class, 'index'])->name('system-health.index');
    Route::post('/system-health/failed-jobs/retry-all', [\App\Http\Controllers\SystemHealthController::class, 'retryAll'])->name('system-health.retry-all');
    Route::post('/system-health/failed-jobs/{id}/retry', [\App\Http\Controllers\SystemHealthController::class, 'retry'])->name('system-health.retry');
    Route::delete('/system-health/failed-jobs/{id}', [\App\Http\Controllers\SystemHealthController::class, 'forget'])->name('system-health.forget');
    Route::get('/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
    Route::get('/holidays/workdays', [\App\Http\Controllers\HolidayController::class, 'workdays'])->name('holidays.workdays');
    Route::post('/holidays', [\App\Http\Controllers\HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/seed-fixed', [\App\Http\Controllers\HolidayController::class, 'seedFixed'])->name('holidays.seed-fixed');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\HolidayController::class, 'destroy'])->name('holidays.destroy');
    Route::get('/reports', [\App\Http\Controllers\FormReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [\App\Http\Controllers\FormReportController::class, 'export'])->name('reports.export');
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
    Route::get('/meeting-rooms/bookings/{booking}/export-ics', [MeetingRoomController::class, 'exportIcs'])->name('meeting-rooms.bookings.export-ics');

    // 企業綜合行事曆看板
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/export-ics', [CalendarController::class, 'exportIcs'])->name('calendar.export-ics');

    // 企業文件庫與檔案版本控制
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/documents/{document}/versions', [DocumentController::class, 'uploadVersion'])->name('documents.versions.upload');
    Route::get('/documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions.list');
    Route::get('/documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/preview/{version?}', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    // 企業同仁投票與意見調查系統
    Route::get('/polls', [PollController::class, 'index'])->name('polls.index');
    Route::post('/polls', [PollController::class, 'store'])->name('polls.store');
    Route::get('/polls/{poll}', [PollController::class, 'show'])->name('polls.show');
    Route::post('/polls/{poll}/vote', [PollController::class, 'vote'])->name('polls.vote');
    Route::post('/polls/{poll}/close', [PollController::class, 'close'])->name('polls.close');
    Route::delete('/polls/{poll}', [PollController::class, 'destroy'])->name('polls.destroy');

    // 企業專案管理與任務看板系統
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.updateStatus');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // 企業固定資產與公務設備借調系統
    Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
    Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
    Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::post('/assets/{asset}/borrow', [AssetController::class, 'borrow'])->name('assets.borrow');
    Route::post('/assets/{asset}/return', [AssetController::class, 'returnAsset'])->name('assets.return');

    // 企業工作週報與主管點評系統
    Route::get('/weekly-reports', [WeeklyReportController::class, 'index'])->name('weekly-reports.index');
    Route::get('/weekly-reports/create', [WeeklyReportController::class, 'create'])->name('weekly-reports.create');
    Route::post('/weekly-reports', [WeeklyReportController::class, 'store'])->name('weekly-reports.store');
    Route::get('/weekly-reports/{weeklyReport}', [WeeklyReportController::class, 'show'])->name('weekly-reports.show');
    Route::get('/weekly-reports/{weeklyReport}/edit', [WeeklyReportController::class, 'edit'])->name('weekly-reports.edit');
    Route::put('/weekly-reports/{weeklyReport}', [WeeklyReportController::class, 'update'])->name('weekly-reports.update');
    Route::delete('/weekly-reports/{weeklyReport}', [WeeklyReportController::class, 'destroy'])->name('weekly-reports.destroy');
    Route::post('/weekly-reports/{weeklyReport}/comment', [WeeklyReportController::class, 'comment'])->name('weekly-reports.comment');
    Route::post('/weekly-reports/remind', [WeeklyReportController::class, 'remind'])->name('weekly-reports.remind');

    // 機密薪資單與薪酬查詢系統
    Route::get('/payslips', [PayslipController::class, 'index'])->name('payslips.index');
    Route::post('/payslips', [PayslipController::class, 'store'])->name('payslips.store');
    Route::post('/payslips/publish-batch', [PayslipController::class, 'publishBatch'])->name('payslips.publishBatch');
    Route::get('/payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::put('/payslips/{payslip}', [PayslipController::class, 'update'])->name('payslips.update');
    Route::delete('/payslips/{payslip}', [PayslipController::class, 'destroy'])->name('payslips.destroy');

    // 企業同仁訂餐與下午茶揪團系統
    Route::get('/meal-orders', [MealOrderController::class, 'index'])->name('meal-orders.index');
    Route::post('/meal-orders', [MealOrderController::class, 'store'])->name('meal-orders.store');
    Route::get('/meal-orders/{mealOrder}', [MealOrderController::class, 'show'])->name('meal-orders.show');
    Route::post('/meal-orders/{mealOrder}/items', [MealOrderController::class, 'addItem'])->name('meal-orders.items.store');
    Route::delete('/meal-orders/{mealOrder}/items/{item}', [MealOrderController::class, 'removeItem'])->name('meal-orders.items.destroy');
    Route::patch('/meal-orders/{mealOrder}/items/{item}/toggle-payment', [MealOrderController::class, 'togglePayment'])->name('meal-orders.items.toggle-payment');
    Route::post('/meal-orders/{mealOrder}/close', [MealOrderController::class, 'close'])->name('meal-orders.close');
    Route::post('/meal-orders/{mealOrder}/complete', [MealOrderController::class, 'complete'])->name('meal-orders.complete');
    Route::post('/meal-orders/{mealOrder}/cancel', [MealOrderController::class, 'cancel'])->name('meal-orders.cancel');
    Route::post('/meal-orders/{mealOrder}/notify', [MealOrderController::class, 'notifyParticipants'])->name('meal-orders.notify');

    // 內部 IT / 總務叫修與服務工單系統
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/attachments/{index}', [TicketController::class, 'attachment'])->where('index', '[0-9]+')->name('tickets.attachment');
    Route::get('/tickets/{ticket}/replies/{reply}/attachments/{index}', [TicketController::class, 'replyAttachment'])->where(['reply' => '[0-9]+', 'index' => '[0-9]+'])->name('tickets.reply-attachment');
    Route::patch('/tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
    Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::post('/tickets/{ticket}/rate', [TicketController::class, 'rate'])->name('tickets.rate');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');

    // 企業訪客預約與門禁貴賓接待系統
    Route::get('/visitor-reservations', [VisitorReservationController::class, 'index'])->name('visitor-reservations.index');
    Route::get('/visitor-reservations/create', [VisitorReservationController::class, 'create'])->name('visitor-reservations.create');
    Route::get('/visitor-reservations/export', [VisitorReservationController::class, 'exportLog'])->name('visitor-reservations.export');
    Route::post('/visitor-reservations', [VisitorReservationController::class, 'store'])->name('visitor-reservations.store');
    Route::get('/visitor-reservations/{visitorReservation}', [VisitorReservationController::class, 'show'])->name('visitor-reservations.show');
    Route::post('/visitor-reservations/{visitorReservation}/check-in', [VisitorReservationController::class, 'checkIn'])->name('visitor-reservations.check-in');
    Route::post('/visitor-reservations/{visitorReservation}/check-out', [VisitorReservationController::class, 'checkOut'])->name('visitor-reservations.check-out');
    Route::post('/visitor-reservations/{visitorReservation}/cancel', [VisitorReservationController::class, 'cancel'])->name('visitor-reservations.cancel');

    // 企業公務車輛借調與出差派車系統
    Route::get('/vehicles', [VehicleDispatchController::class, 'index'])->name('vehicles.index');
    Route::get('/vehicles/create', [VehicleDispatchController::class, 'create'])->name('vehicles.create');
    Route::get('/vehicles/export', [VehicleDispatchController::class, 'exportLog'])->name('vehicles.export');
    Route::post('/vehicles', [VehicleDispatchController::class, 'store'])->name('vehicles.store');
    Route::get('/vehicles/{vehicleDispatch}', [VehicleDispatchController::class, 'show'])->name('vehicles.show');
    Route::match(['post', 'patch'], '/vehicles/{vehicleDispatch}/approve', [VehicleDispatchController::class, 'approve'])->name('vehicles.approve');
    Route::match(['post', 'patch'], '/vehicles/{vehicleDispatch}/reject', [VehicleDispatchController::class, 'reject'])->name('vehicles.reject');
    Route::post('/vehicles/{vehicleDispatch}/start', [VehicleDispatchController::class, 'startTrip'])->name('vehicles.start');
    Route::post('/vehicles/{vehicleDispatch}/return', [VehicleDispatchController::class, 'returnTrip'])->name('vehicles.return');
    Route::post('/vehicles/{vehicleDispatch}/cancel', [VehicleDispatchController::class, 'cancel'])->name('vehicles.cancel');

    // 企業內訓課程與員工學習學分系統
    Route::get('/trainings', [\App\Http\Controllers\TrainingCourseController::class, 'index'])->name('trainings.index');
    Route::get('/trainings/create', [\App\Http\Controllers\TrainingCourseController::class, 'create'])->name('trainings.create');
    Route::get('/trainings/export', [\App\Http\Controllers\TrainingCourseController::class, 'exportLog'])->name('trainings.export');
    Route::post('/trainings', [\App\Http\Controllers\TrainingCourseController::class, 'store'])->name('trainings.store');
    Route::get('/trainings/{trainingCourse}', [\App\Http\Controllers\TrainingCourseController::class, 'show'])->name('trainings.show');
    Route::post('/trainings/{trainingCourse}/register', [\App\Http\Controllers\TrainingCourseController::class, 'register'])->name('trainings.register');
    Route::post('/trainings/{trainingCourse}/cancel', [\App\Http\Controllers\TrainingCourseController::class, 'cancelRegistration'])->name('trainings.cancel');
    Route::post('/trainings/{trainingCourse}/check-in/{registration}', [\App\Http\Controllers\TrainingCourseController::class, 'checkIn'])->name('trainings.check-in');
    Route::post('/trainings/{trainingCourse}/feedback', [\App\Http\Controllers\TrainingCourseController::class, 'submitFeedback'])->name('trainings.feedback');
    Route::patch('/trainings/{trainingCourse}/status', [\App\Http\Controllers\TrainingCourseController::class, 'updateStatus'])->name('trainings.status');

    // 企業感謝幣文化牆與福利點數商城
    Route::get('/recognition', [\App\Http\Controllers\RecognitionController::class, 'index'])->name('recognition.index');
    Route::post('/recognition', [\App\Http\Controllers\RecognitionController::class, 'store'])->name('recognition.store');
    Route::post('/recognition/{recognitionPost}/like', [\App\Http\Controllers\RecognitionController::class, 'like'])->name('recognition.like');
    Route::delete('/recognition/{recognitionPost}', [\App\Http\Controllers\RecognitionController::class, 'destroy'])->name('recognition.destroy');
    Route::get('/welfare', [\App\Http\Controllers\WelfareController::class, 'index'])->name('welfare.index');
    Route::post('/welfare/rewards', [\App\Http\Controllers\WelfareController::class, 'storeReward'])->name('welfare.rewards.store');
    Route::patch('/welfare/rewards/{welfareReward}/toggle', [\App\Http\Controllers\WelfareController::class, 'toggleReward'])->name('welfare.rewards.toggle');
    Route::post('/welfare/rewards/{welfareReward}/redeem', [\App\Http\Controllers\WelfareController::class, 'redeem'])->name('welfare.redeem');
    Route::post('/welfare/redemptions/{welfareRedemption}/fulfill', [\App\Http\Controllers\WelfareController::class, 'fulfill'])->name('welfare.fulfill');
    Route::post('/welfare/redemptions/{welfareRedemption}/cancel', [\App\Http\Controllers\WelfareController::class, 'cancel'])->name('welfare.cancel');

    // 站內通知中心
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // 品牌設定:網站名稱 / Logo / Favicon (管理員專屬)
    Route::get('/branding', [\App\Http\Controllers\BrandingController::class, 'edit'])->name('branding.edit');
    Route::post('/branding', [\App\Http\Controllers\BrandingController::class, 'update'])->name('branding.update');

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
    Route::get('/org-management/import', [\App\Http\Controllers\UserImportController::class, 'show'])->name('org-management.import');
    Route::get('/org-management/import/template', [\App\Http\Controllers\UserImportController::class, 'template'])->name('org-management.import.template');
    Route::post('/org-management/import', [\App\Http\Controllers\UserImportController::class, 'store'])->name('org-management.import.store');
    Route::post('/org-management/users', [OrgManagementController::class, 'storeUser'])->name('org-management.users.store');
    Route::put('/org-management/users/{user}', [OrgManagementController::class, 'updateUser'])->name('org-management.users.update');
    Route::post('/org-management/users/{user}/reset-password', [OrgManagementController::class, 'resetPassword'])->name('org-management.users.reset-password');
    Route::post('/org-management/users/{user}/reset-two-factor', [OrgManagementController::class, 'resetTwoFactor'])->name('org-management.users.reset-two-factor');
    Route::post('/org-management/users/{user}/status', [OrgManagementController::class, 'updateUserStatus'])->name('org-management.users.status');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/logout-other-devices', [\App\Http\Controllers\ProfileController::class, 'logoutOtherDevices'])->name('profile.logout-other-devices');
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::post('/push/test', [\App\Http\Controllers\PushSubscriptionController::class, 'test'])->middleware('throttle:6,1')->name('push.test');
    Route::put('/profile/notification-preferences', [\App\Http\Controllers\ProfileController::class, 'updateNotificationPreferences'])->name('profile.notification-preferences');
});

require __DIR__.'/auth.php';
