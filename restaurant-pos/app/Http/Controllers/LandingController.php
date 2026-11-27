<?php

namespace App\Http\Controllers;

use App\Models\Central\Plan;

class LandingController extends Controller
{
    public function __invoke()
    {
        return view('saas.landing', [
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'trialDays' => config('tenancy.trial_days'),
        ]);
    }
}
