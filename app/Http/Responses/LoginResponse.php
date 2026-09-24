<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): RedirectResponse
    {
        $user = $request->user();

        // Redirect super admin to admin panel
        if ($user && $user->hasRole('super_admin')) {
            return redirect('/admin/dashboard');
        }

        // Regular users go to architect dashboard
        return redirect('/dashboard');
    }
}
