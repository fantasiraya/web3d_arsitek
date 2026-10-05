<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old local avatar (skip if it's a Google/external URL)
        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Compress & resize, then store
        $path = $this->storeCompressedAvatar($request->file('avatar'));

        $user->avatar = $path;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui.']);

        return to_route('profile.edit');
    }

    /**
     * Compress, resize (max 400×400), and store avatar using GD.
     * Always outputs as JPEG for consistent small file size.
     */
    private function storeCompressedAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        $quality   = 82;   // JPEG quality (0–100)
        $maxDim    = 400;  // max width/height in pixels

        $mime = $file->getMimeType();

        // Load source image from GD
        $source = match (true) {
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => imagecreatefromjpeg($file->getRealPath()),
            str_contains($mime, 'png')  => imagecreatefrompng($file->getRealPath()),
            str_contains($mime, 'webp') => imagecreatefromwebp($file->getRealPath()),
            default                     => imagecreatefromjpeg($file->getRealPath()),
        };

        [$srcW, $srcH] = getimagesize($file->getRealPath());

        // Calculate target dimensions (keep aspect ratio, cap at maxDim)
        $ratio  = min($maxDim / $srcW, $maxDim / $srcH, 1.0); // never upscale
        $dstW   = (int) round($srcW * $ratio);
        $dstH   = (int) round($srcH * $ratio);

        // Create destination canvas
        $canvas = imagecreatetruecolor($dstW, $dstH);

        // Preserve transparency for PNG sources before resizing
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        // Write compressed JPEG to a temp file, then move to storage
        $tmpPath  = sys_get_temp_dir() . '/' . uniqid('avatar_', true) . '.jpg';
        imagejpeg($canvas, $tmpPath, $quality);
        imagedestroy($canvas);

        // Store under avatars/ on public disk
        $storagePath = 'avatars/' . basename($tmpPath);
        Storage::disk('public')->put($storagePath, file_get_contents($tmpPath));
        @unlink($tmpPath);

        return $storagePath;
    }

    /**
     * Remove the user's custom avatar (revert to initials).
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto profil dihapus.']);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
