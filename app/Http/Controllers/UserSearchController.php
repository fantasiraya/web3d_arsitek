<?php

namespace App\Http\Controllers;

use App\Domains\Auth\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    /**
     * GET /users/search?email=xxx
     * Suggest registered users by email (LIKE %email%), max 5 results.
     * Excludes the requesting user from results.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->query('email', ''));

        if (strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::where('email', 'LIKE', '%' . $query . '%')
            ->where('id', '!=', $request->user()->id)
            ->select('id', 'name', 'email')
            ->orderByRaw('CASE WHEN email LIKE ? THEN 0 ELSE 1 END', [$query . '%'])
            ->limit(5)
            ->get()
            ->map(fn ($u) => [
                'id'      => $u->id,
                'name'    => $u->name,
                'email'   => $u->email,
                'initial' => strtoupper(substr($u->name, 0, 1)),
            ]);

        return response()->json(['data' => $users]);
    }
}
