<?php

use App\Domains\Auth\Models\User;
use App\Http\Controllers\Project\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/projects', [ProjectController::class, 'index'])->name('api.projects.index');
});

Route::post('/sanctum/token', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'device_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        abort(401);
    }

    $token = $user->createToken($request->device_name);

    return ['token' => $token->plainTextToken];
});

// ── Wilayah Indonesia (laravolt/indonesia) ─────────────────────────────────
// Pakai middleware 'auth' (session-based via web guard) agar hanya user login yang bisa akses
// Data di-cache 24 jam di server, jadi aman dari abuse
use App\Http\Controllers\IndonesiaRegionController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/provinces', [IndonesiaRegionController::class, 'provinces'])->name('api.provinces');
    Route::get('/cities',    [IndonesiaRegionController::class, 'cities'])->name('api.cities');
    Route::get('/districts', [IndonesiaRegionController::class, 'districts'])->name('api.districts');
    Route::get('/villages',  [IndonesiaRegionController::class, 'villages'])->name('api.villages');
});
