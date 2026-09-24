<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Users Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
    Route::post('/users/{user}/plan', [UserController::class, 'changePlan'])->name('users.plan');
    Route::post('/users/{user}/limit', [UserController::class, 'setLimitOverride'])->name('users.limit');
    Route::delete('/users/{user}/limit', [UserController::class, 'removeLimitOverride'])->name('users.limit.remove');

    // Projects Monitoring
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');

    // Subscriptions Management
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::post('/subscriptions/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscriptions.change');

    // Plans Management
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::patch('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
