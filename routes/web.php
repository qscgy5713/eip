<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormRequestController;
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

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
