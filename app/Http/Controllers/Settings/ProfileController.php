<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information (name + email).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Upload or replace the user's avatar photo.
     * Old local file is always deleted before saving the new one.
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Always delete the old local file before replacing
        $this->deleteLocalAvatar($user->avatar);

        // Compress, resize, and store the new file
        $path = $this->storeCompressedAvatar($request->file('avatar'));

        $user->avatar = $path;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui.']);

        return to_route('profile.edit');
    }

    /**
     * Remove the user's custom avatar and delete the physical file.
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Delete file from disk first
        $this->deleteLocalAvatar($user->avatar);

        // Clear DB column regardless of delete result
        $user->avatar = null;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil dihapus.']);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account, including all physical files on disk.
     *
     * IMPORTANT: We must manually delete each Project via Eloquent so the
     * Project::deleting and ProjectVersion::deleting hooks fire and clean up
     * the physical GLB files from storage. If we relied solely on the DB-level
     * cascadeOnDelete() constraint, those hooks would be bypassed entirely.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // 1. Delete avatar file from disk (skips external Google URLs)
        $this->deleteLocalAvatar($user->avatar);

        // 2. Delete each owned project through Eloquent so Project::deleting
        //    and ProjectVersion::deleting hooks fire and remove GLB files.
        $user->projects()->with('versions')->each(function ($project): void {
            $project->delete();
        });

        // 3. Destroy the account
        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────

    /**
     * Delete a locally-stored avatar file from the public disk.
     *
     * Skips silently for:
     *   - null / empty paths
     *   - external URLs (Google OAuth avatars, etc.)
     *   - paths outside the avatars/ directory (path traversal guard)
     *
     * @return bool  true if a local file was successfully deleted
     */
    private function deleteLocalAvatar(?string $avatarPath): bool
    {
        if (empty($avatarPath)) {
            return false;
        }

        // External URL — never touch the remote resource
        if (str_starts_with($avatarPath, 'http://') || str_starts_with($avatarPath, 'https://')) {
            return false;
        }

        // Normalise and guard against path traversal
        $normalized = ltrim($avatarPath, '/');

        if (!str_starts_with($normalized, 'avatars/')) {
            Log::warning("[ProfileController] Skipped avatar deletion — unexpected path: {$avatarPath}");
            return false;
        }

        // Nothing to delete if the file doesn't exist on disk
        if (!Storage::disk('public')->exists($normalized)) {
            return false;
        }

        $deleted = Storage::disk('public')->delete($normalized);

        if (!$deleted) {
            Log::error("[ProfileController] Failed to delete avatar file: {$normalized}");
        }

        return $deleted;
    }

    /**
     * Compress and resize an uploaded image using GD (no extra packages needed).
     *
     * Rules:
     *   - Max dimension: 400 × 400 px (aspect ratio preserved, never upscaled)
     *   - Output format: JPEG, quality 82
     *   - PNG transparency is converted to a white background before saving
     *
     * @return string  Relative storage path, e.g. "avatars/avatar_xxx.jpg"
     */
    private function storeCompressedAvatar(UploadedFile $file): string
    {
        $quality = 82;
        $maxDim  = 400;

        $mime = $file->getMimeType();

        $source = match (true) {
            str_contains((string) $mime, 'png')  => imagecreatefrompng($file->getRealPath()),
            str_contains((string) $mime, 'webp') => imagecreatefromwebp($file->getRealPath()),
            default                               => imagecreatefromjpeg($file->getRealPath()),
        };

        /** @var array{int, int} $dims */
        [$srcW, $srcH] = getimagesize($file->getRealPath());

        // Calculate target size — never upscale
        $ratio = min($maxDim / $srcW, $maxDim / $srcH, 1.0);
        $dstW  = (int) round($srcW * $ratio);
        $dstH  = (int) round($srcH * $ratio);

        // Create a true-colour canvas with a white background (safe for JPEG output)
        $canvas = imagecreatetruecolor($dstW, $dstH);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        // Write to a temp file, then move to Storage
        $tmpPath     = sys_get_temp_dir() . '/' . uniqid('avatar_', true) . '.jpg';
        imagejpeg($canvas, $tmpPath, $quality);
        imagedestroy($canvas);

        $storagePath = 'avatars/' . basename($tmpPath);
        Storage::disk('public')->put($storagePath, file_get_contents($tmpPath));
        @unlink($tmpPath);

        return $storagePath;
    }
}
