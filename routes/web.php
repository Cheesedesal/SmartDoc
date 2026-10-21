<?php

use App\Http\Controllers\AccountSettingsController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AdminDataImportController;
use App\Http\Controllers\Admin\AnnouncementManagementController;
use App\Http\Controllers\Admin\ArchiveController;
use App\Http\Controllers\Admin\ChatbotFaqController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DocumentTypeManagementController;
use App\Http\Controllers\Admin\ProfileOptionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\RequestWorkflowController;
use App\Http\Controllers\StudentDocumentRequestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware(['auth', \App\Http\Middleware\EnsureUserIsNotArchived::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/settings', [AccountSettingsController::class, 'edit'])->name('account.settings');
    Route::put('/account/settings', [AccountSettingsController::class, 'update'])->name('account.settings.update');
    Route::post('/account/settings/password-reset', [PasswordResetController::class, 'sendAuthenticatedLink'])
        ->middleware('throttle:3,1')
        ->name('account.password-reset.email');
    Route::get('/account/avatar', [AccountSettingsController::class, 'avatar'])->name('account.avatar');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');
    Route::post('/chatbot/message', [ChatbotController::class, 'ask'])
        ->middleware('throttle:10,1')
        ->name('chatbot.ask');
    Route::delete('/chatbot/history', [ChatbotController::class, 'clearHistory'])
        ->name('chatbot.history.clear');
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/request-files/{documentRequest}/{upload}', [RequestWorkflowController::class, 'download'])->name('request-files.download');

    Route::middleware('role:student')->group(function () {
        Route::get('/requests', [StudentDocumentRequestController::class, 'index'])->name('requests.index');
        Route::post('/requests', [StudentDocumentRequestController::class, 'store'])->name('requests.store');
        Route::post('/requests/{documentRequest}/requirements/{upload}', [StudentDocumentRequestController::class, 'replaceRequirement'])->name('requests.requirements.replace');
        Route::get('/requests/{documentRequest}', [StudentDocumentRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{documentRequest}/release', [RequestWorkflowController::class, 'release'])->name('requests.release');
    });

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/request-management', [RequestWorkflowController::class, 'index'])->name('request-management.index');
        Route::get('/request-management/{documentRequest}', [RequestWorkflowController::class, 'show'])->name('request-management.show');
        Route::post('/request-management/{documentRequest}/requirements/{upload}', [RequestWorkflowController::class, 'verify'])->name('request-management.verify');
        Route::post('/request-management/{documentRequest}/requirements/{upload}/ai-review', [RequestWorkflowController::class, 'aiReview'])->name('request-management.ai-review');
        Route::post('/request-management/{documentRequest}/start', [RequestWorkflowController::class, 'start'])->name('request-management.start');
        Route::post('/request-management/{documentRequest}/advance', [RequestWorkflowController::class, 'advance'])->name('request-management.advance');
        Route::get('/request-records', [RequestWorkflowController::class, 'records'])->name('request-records.index');
        Route::get('/request-records/export', [RequestWorkflowController::class, 'exportRecords'])->name('request-records.export');
    });

    Route::prefix('/admin/chatbot/faqs')
        ->name('admin.chatbot-faqs.')
        ->middleware('role:admin')
        ->controller(ChatbotFaqController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/recommendations/{intent}', 'createRecommendation')->name('recommendations.create');
            Route::get('/{chatbotFaq}/edit', 'edit')->name('edit');
            Route::put('/{chatbotFaq}', 'update')->name('update');
            Route::post('/{chatbotFaq}/approve', 'approve')->name('approve');
            Route::patch('/{chatbotFaq}/active', 'setActive')->name('active');
        });

    Route::prefix('/admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(function () {
            Route::get('/profile-options/{type}', [ProfileOptionController::class, 'index'])->name('profile-options.index');
            Route::post('/profile-options/{type}', [ProfileOptionController::class, 'store'])->name('profile-options.store');
            Route::put('/profile-options/{type}/{id}', [ProfileOptionController::class, 'update'])->whereNumber('id')->name('profile-options.update');
            Route::post('/profile-options/{type}/{id}/archive', [ProfileOptionController::class, 'archive'])->whereNumber('id')->name('profile-options.archive');
            Route::post('/profile-options/{type}/{id}/restore', [ProfileOptionController::class, 'restore'])->whereNumber('id')->name('profile-options.restore');
            Route::get('/archive', [ArchiveController::class, 'index'])->name('archive.index');
            Route::post('/archive/{type}/{id}/restore', [ArchiveController::class, 'restore'])->whereNumber('id')->name('archive.restore');
            Route::get('/imports/{type}', [AdminDataImportController::class, 'show'])->name('imports.show');
            Route::get('/imports/{type}/template', [AdminDataImportController::class, 'template'])->name('imports.template');
            Route::post('/imports/{type}/preview', [AdminDataImportController::class, 'preview'])->name('imports.preview');
            Route::post('/imports/{type}/commit', [AdminDataImportController::class, 'import'])->name('imports.commit');
            Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::post('/departments/{department}/archive', [DepartmentController::class, 'archive'])->name('departments.archive');
            Route::post('/departments/{department}/restore', [DepartmentController::class, 'restore'])->name('departments.restore');
            Route::post('/departments/bulk-archive', [DepartmentController::class, 'bulkArchive'])->name('departments.bulk-archive');
            Route::post('/departments/bulk-restore', [DepartmentController::class, 'bulkRestore'])->name('departments.bulk-restore');

            Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
            Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
            Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/archive', [UserManagementController::class, 'archive'])->name('users.archive');
            Route::post('/users/{user}/restore', [UserManagementController::class, 'restore'])->name('users.restore');
            Route::post('/users/bulk-archive', [UserManagementController::class, 'bulkArchive'])->name('users.bulk-archive');
            Route::post('/users/bulk-restore', [UserManagementController::class, 'bulkRestore'])->name('users.bulk-restore');
            Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');

            Route::get('/announcements', [AnnouncementManagementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AnnouncementManagementController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{announcement}', [AnnouncementManagementController::class, 'update'])->name('announcements.update');
            Route::post('/announcements/{announcement}/archive', [AnnouncementManagementController::class, 'archive'])->name('announcements.archive');
            Route::post('/announcements/{announcement}/restore', [AnnouncementManagementController::class, 'restore'])->name('announcements.restore');
            Route::post('/announcements/bulk-archive', [AnnouncementManagementController::class, 'bulkArchive'])->name('announcements.bulk-archive');
            Route::post('/announcements/bulk-restore', [AnnouncementManagementController::class, 'bulkRestore'])->name('announcements.bulk-restore');

            Route::get('/document-types', [DocumentTypeManagementController::class, 'index'])->name('document-types.index');
            Route::post('/document-types', [DocumentTypeManagementController::class, 'store'])->name('document-types.store');
            Route::put('/document-types/{documentType}', [DocumentTypeManagementController::class, 'update'])->name('document-types.update');
            Route::post('/document-types/{documentType}/archive', [DocumentTypeManagementController::class, 'archive'])->name('document-types.archive');
            Route::post('/document-types/{documentType}/restore', [DocumentTypeManagementController::class, 'restore'])->name('document-types.restore');
            Route::post('/document-types/bulk-archive', [DocumentTypeManagementController::class, 'bulkArchive'])->name('document-types.bulk-archive');
            Route::post('/document-types/bulk-restore', [DocumentTypeManagementController::class, 'bulkRestore'])->name('document-types.bulk-restore');
            Route::post('/document-types/{documentType}/requirements', [DocumentTypeManagementController::class, 'storeRequirement'])->name('document-types.requirements.store');
            Route::put('/document-types/{documentType}/requirements/{requirement}', [DocumentTypeManagementController::class, 'updateRequirement'])->name('document-types.requirements.update');
            Route::post('/document-types/{documentType}/requirements/{requirement}/archive', [DocumentTypeManagementController::class, 'archiveRequirement'])->name('document-types.requirements.archive');
            Route::post('/document-types/{documentType}/requirements/{requirement}/restore', [DocumentTypeManagementController::class, 'restoreRequirement'])->name('document-types.requirements.restore');
            Route::post('/document-types/bulk-requirements-archive', [DocumentTypeManagementController::class, 'bulkArchiveRequirements'])->name('document-types.requirements.bulk-archive');
            Route::post('/document-types/bulk-requirements-restore', [DocumentTypeManagementController::class, 'bulkRestoreRequirements'])->name('document-types.requirements.bulk-restore');

            Route::post('/chatbot/faqs/{chatbotFaq}/archive', [ChatbotFaqController::class, 'archive'])->name('chatbot-faqs.archive');
            Route::post('/chatbot/faqs/{chatbotFaq}/restore', [ChatbotFaqController::class, 'restore'])->name('chatbot-faqs.restore');
            Route::post('/chatbot/faqs/bulk-archive', [ChatbotFaqController::class, 'bulkArchive'])->name('chatbot-faqs.bulk-archive');
            Route::post('/chatbot/faqs/bulk-restore', [ChatbotFaqController::class, 'bulkRestore'])->name('chatbot-faqs.bulk-restore');
        });
});
