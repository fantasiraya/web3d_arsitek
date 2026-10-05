<?php

namespace App\Http\Controllers\Auth;

use App\Domains\Auth\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     * If user doesn't exist, auto-create account (no password needed).
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['google' => 'Gagal autentikasi dengan Google. Silakan coba lagi.']);
        }

        // Create or update user — auto-register if not found
        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name'               => $googleUser->getName(),
                'google_id'          => $googleUser->getId(),
                'avatar'             => $googleUser->getAvatar(),
                'password'           => null,          // OAuth user — no password required
                'email_verified_at'  => now(),         // Google already verified the email
            ]
        );

        Auth::login($user, remember: true);

        // Redirect super_admin to admin panel, others to dashboard
        if ($user->hasRole('super_admin', 'web')) {
            return redirect('/admin/dashboard');
        }

        return redirect()->intended('/dashboard');
    }
}
