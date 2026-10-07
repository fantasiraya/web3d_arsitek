<?php

use App\Domains\Comment\Controllers\CommentController;
use App\Domains\Comment\Controllers\PinCommentController;
use App\Domains\Chat\Controllers\ChatController;
use App\Domains\Project\Controllers\CameraPresetController;
use App\Domains\Rab\Controllers\RabDocumentController;
use App\Domains\Rab\Controllers\RabItemController;
use App\Domains\Rab\Controllers\RabPriceItemController;
use App\Domains\Rab\Controllers\RabTemplateController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\UserSearchController;
use App\Http\Controllers\ProjectsPageController;
use App\Http\Controllers\TeamsPageController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\WelcomeController::class)->name('home');
Route::inertia('/showcase', 'ShowcaseDemo')->name('showcase');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // User email search (for invite suggestions)
    Route::get('/users/search', UserSearchController::class)->name('users.search');

    // Proyek 3D page
    Route::get('/projects', [ProjectsPageController::class, 'index'])->name('projects.index');

    // Tim & Klien page
    Route::get('/teams', [TeamsPageController::class, 'index'])->name('teams.index');

    // Riwayat pembelian paket
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/plans', PlansController::class)->name('plans.index');

    // Project management
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::post('/projects/{project}/invite', [ProjectController::class, 'inviteClient'])->name('projects.invite');
    Route::delete('/projects/{project}/clients/{client}', [ProjectController::class, 'revokeClient'])->name('projects.clients.revoke');
    Route::post('/projects/{project}/accept-invitation', [ProjectController::class, 'acceptInvitation'])->name('projects.accept-invitation');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';

Route::middleware(['auth', 'project.access', 'project.revision_limit'])->post('/projects/{project}/comments', [PinCommentController::class, 'store'])->name('projects.comments.store');
Route::middleware(['auth', 'project.access'])->patch('/projects/{project}/comments/{comment}', [PinCommentController::class, 'update'])->name('projects.comments.update');
Route::middleware(['auth', 'project.access'])->patch('/projects/{project}/comments/{comment}/resolve', [PinCommentController::class, 'toggleResolved'])->name('projects.comments.resolve');
Route::middleware(['auth', 'project.access'])->delete('/projects/{project}/comments/{comment}', [PinCommentController::class, 'destroy'])->name('projects.comments.destroy');

// Viewer — akses dikontrol langsung di ViewerController (owner + client + super_admin)
Route::middleware(['auth'])->get('/projects/{project}/viewer', [ViewerController::class, 'show'])->name('projects.viewer');

Route::middleware(['auth', 'project.access'])->get('/projects/{project}/comments', [CommentController::class, 'index'])->name('projects.comments.index');

// ── Camera Presets ──
Route::middleware(['auth', 'project.access'])->group(function () {
    Route::get('/projects/{project}/camera-presets', [CameraPresetController::class, 'index'])->name('projects.camera-presets.index');
    Route::post('/projects/{project}/camera-presets', [CameraPresetController::class, 'store'])->name('projects.camera-presets.store');
    Route::delete('/projects/{project}/camera-presets/{preset}', [CameraPresetController::class, 'destroy'])->name('projects.camera-presets.destroy');
});

// ── RAB & Lembar Kerja ──
Route::middleware(['auth', 'verified'])->group(function () {
    // Master harga satuan (milik user, tidak terikat project)
    Route::get('/rab/price-items', [RabPriceItemController::class, 'index'])->name('rab.price-items.index');
    Route::post('/rab/price-items', [RabPriceItemController::class, 'store'])->name('rab.price-items.store');
    Route::put('/rab/price-items/{priceItem}', [RabPriceItemController::class, 'update'])->name('rab.price-items.update');
    Route::delete('/rab/price-items/{priceItem}', [RabPriceItemController::class, 'destroy'])->name('rab.price-items.destroy');

    // Template RAB (milik user, tidak terikat project)
    Route::get('/rab/templates', [RabTemplateController::class, 'index'])->name('rab.templates.index');
    Route::post('/rab/templates', [RabTemplateController::class, 'store'])->name('rab.templates.store');
    Route::get('/rab/templates/{template}', [RabTemplateController::class, 'show'])->name('rab.templates.show');
    Route::put('/rab/templates/{template}', [RabTemplateController::class, 'update'])->name('rab.templates.update');
    Route::delete('/rab/templates/{template}', [RabTemplateController::class, 'destroy'])->name('rab.templates.destroy');

    // Dokumen RAB (terikat project)
    Route::get('/projects/{project}/rab', [RabDocumentController::class, 'index'])->name('rab.index');
    Route::post('/projects/{project}/rab', [RabDocumentController::class, 'store'])->name('rab.store');
    Route::get('/projects/{project}/rab/{rab}', [RabDocumentController::class, 'show'])->name('rab.show');
    Route::put('/projects/{project}/rab/{rab}', [RabDocumentController::class, 'update'])->name('rab.update');
    Route::delete('/projects/{project}/rab/{rab}', [RabDocumentController::class, 'destroy'])->name('rab.destroy');
    Route::post('/projects/{project}/rab/{rab}/finalize', [RabDocumentController::class, 'finalize'])->name('rab.finalize');
    Route::post('/projects/{project}/rab/{rab}/reopen', [RabDocumentController::class, 'reopen'])->name('rab.reopen');
    Route::patch('/projects/{project}/rab/{rab}/visibility', [RabDocumentController::class, 'toggleVisibility'])->name('rab.visibility');

    // Item RAB (terikat dokumen RAB)
    Route::post('/projects/{project}/rab/{rab}/items', [RabItemController::class, 'store'])->name('rab.items.store');
    Route::put('/projects/{project}/rab/{rab}/items/{item}', [RabItemController::class, 'update'])->name('rab.items.update');
    Route::delete('/projects/{project}/rab/{rab}/items/{item}', [RabItemController::class, 'destroy'])->name('rab.items.destroy');
});

// ── Chat realtime routes ──
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/projects/{project}/chat', [ChatController::class, 'index'])->name('projects.chat.index');
    Route::post('/projects/{project}/chat', [ChatController::class, 'store'])->name('projects.chat.store');
    Route::patch('/projects/{project}/chat/read', [ChatController::class, 'markRead'])->name('projects.chat.read');
});

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
});

// ── Checkout routes — specific routes HARUS sebelum wildcard {plan} ──
// Midtrans webhook — tidak butuh auth/CSRF
Route::post('/checkout/midtrans/notification', [CheckoutController::class, 'notification'])
    ->name('checkout.notification')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
// Payment callback dari Snap onSuccess (client-side)
Route::middleware(['auth'])->post('/checkout/payment-callback', [CheckoutController::class, 'paymentCallback'])
    ->name('checkout.payment-callback');
// Halaman pending transfer
Route::get('/checkout/pending/{transaction}', [CheckoutController::class, 'pending'])->name('checkout.pending');
// Wildcard plan — harus paling terakhir
Route::get('/checkout/{plan}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::middleware(['auth'])->post('/checkout/{plan}/order', [CheckoutController::class, 'order'])->name('checkout.order');
