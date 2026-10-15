<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PlansController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Plans', [
            'plans' => Plan::getActivePlansForDisplay(),
        ]);
    }
}
