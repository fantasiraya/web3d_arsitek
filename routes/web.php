<?php

use App\Domains\Comment\Controllers\CommentController;
use App\Domains\Comment\Controllers\PinCommentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ViewerController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Project management
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/invite', [ProjectController::class, 'inviteClient'])->name('projects.invite');
    Route::delete('/projects/{project}/clients/{client}', [ProjectController::class, 'revokeClient'])->name('projects.clients.revoke');
    Route::post('/projects/{project}/accept-invitation', [ProjectController::class, 'acceptInvitation'])->name('projects.accept-invitation');
});

require __DIR__.'/settings.php';

Route::middleware(['auth', 'project.access', 'project.revision_limit'])->post('/projects/{project}/comments', [PinCommentController::class, 'store'])->name('projects.comments.store');

Route::middleware(['auth', 'project.access'])->get('/projects/{project}/viewer', [ViewerController::class, 'show'])->name('projects.viewer');

Route::middleware(['auth', 'project.access'])->get('/projects/{project}/comments', [CommentController::class, 'index'])->name('projects.comments.index');

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});
