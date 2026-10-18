<?php

use App\Http\Controllers\Admin\AnnouncementManagementController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\DocumentTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RequestFileController;
use App\Http\Controllers\RequestRecordController;
use App\Http\Controllers\StaffRequestController;
use App\Http\Controllers\StudentRequestController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Every role may read announcements; what each role sees is enforced in Announcement::visibleTo().
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');

    // Students: own requests only (ownership re-checked by DocumentRequestPolicy).
    Route::middleware('role:student')
        ->prefix('my-requests')
        ->name('student.requests.')
        ->group(function () {
            Route::get('/', [StudentRequestController::class, 'index'])->name('index');
            Route::get('/create', [StudentRequestController::class, 'create'])->name('create');
            Route::post('/', [StudentRequestController::class, 'store'])->middleware('throttle:10,1')->name('store');
            Route::get('/{documentRequest}', [StudentRequestController::class, 'show'])->name('show');
            Route::post('/{documentRequest}/cancel', [StudentRequestController::class, 'cancel'])->name('cancel');
            Route::post('/{documentRequest}/payment', [StudentRequestController::class, 'resubmitPayment'])->name('payment');
            Route::post('/{documentRequest}/receive', [StudentRequestController::class, 'receive'])->name('receive');
            Route::post('/{documentRequest}/archive', [StudentRequestController::class, 'archive'])->name('archive');
        });

    // Staff and admins: read-only monitoring. No status-changing routes exist by design.
    Route::middleware('role:staff,admin')
        ->prefix('staff/requests')
        ->name('staff.requests.')
        ->group(function () {
            Route::get('/', [StaffRequestController::class, 'index'])->name('index');
            Route::get('/{documentRequest}', [StaffRequestController::class, 'show'])->name('show');
        });

    // Request record history: read-only, staff and admins (port of reference requestrecords.php).
    Route::get('/request-records', [RequestRecordController::class, 'index'])
        ->middleware('role:staff,admin')
        ->name('request-records.index');

    // Administration (port of the reference admin modules). Role-gated server-side; FormRequests re-check admin.
    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {
            Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');
            Route::post('document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
            Route::put('document-types/{documentType}', [DocumentTypeController::class, 'update'])->name('document-types.update');
            Route::delete('document-types/{documentType}', [DocumentTypeController::class, 'destroy'])->name('document-types.destroy');
            Route::get('document-types/{documentType}/configure', [DocumentTypeController::class, 'configure'])->name('document-types.configure');
            Route::post('document-types/{documentType}/requirements', [DocumentTypeController::class, 'storeRequirement'])->name('document-types.requirements.store');
            Route::put('requirements/{requirement}', [DocumentTypeController::class, 'updateRequirement'])->name('requirements.update');
            Route::delete('requirements/{requirement}', [DocumentTypeController::class, 'destroyRequirement'])->name('requirements.destroy');

            Route::get('announcements', [AnnouncementManagementController::class, 'index'])->name('announcements.index');
            Route::post('announcements', [AnnouncementManagementController::class, 'store'])->name('announcements.store');
            Route::put('announcements/{announcement}', [AnnouncementManagementController::class, 'update'])->name('announcements.update');
            Route::delete('announcements/{announcement}', [AnnouncementManagementController::class, 'destroy'])->name('announcements.destroy');
        });

    // Private file access (owner, or staff/admin; checked in the controller via the policy).
    Route::get('/requests/{documentRequest}/requirements/{upload}', [RequestFileController::class, 'requirement'])
        ->name('requests.requirement');
    Route::get('/requests/{documentRequest}/payment-proof', [RequestFileController::class, 'paymentProof'])
        ->name('requests.payment-proof');
});
