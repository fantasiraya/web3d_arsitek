<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Welcome', [
            'plans' => Plan::getActivePlansForDisplay(),
        ]);
    }
}
