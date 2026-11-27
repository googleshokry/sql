<?php

namespace App\Http\Controllers;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Tenancy\TenantRegistrar;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignupController extends Controller
{
    public function create(Request $request)
    {
        return view('saas.signup', [
            'plans' => Plan::where('is_active', true)->orderBy('sort')->get(),
            'selectedPlan' => (int) $request->query('plan'),
            'trialDays' => config('tenancy.trial_days'),
        ]);
    }

    public function store(Request $request, TenantRegistrar $registrar)
    {
        $request->merge(['slug' => strtolower(trim((string) $request->input('slug')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => TenantRegistrar::slugRules(),
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_email' => ['required', 'email', 'max:150'],
            'owner_phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_id' => ['required', Rule::exists(Plan::class, 'id')->where('is_active', true)],
            'demo' => ['nullable', 'boolean'],
        ], [], [
            'name' => __('اسم المطعم'),
            'slug' => __('كود المطعم'),
            'owner_name' => __('اسمك'),
            'owner_email' => __('البريد'),
            'owner_phone' => __('الموبايل'),
            'password' => __('كلمة المرور'),
        ]);

        $tenant = $registrar->register([...$data, 'demo' => $request->boolean('demo')]);

        return redirect($tenant->url('login'))->with('signup_done', true);
    }

    /** "دخول مطعمك": كود المطعم ← صفحة الدخول */
    public function find(Request $request)
    {
        $slug = strtolower(trim((string) $request->query('code')));
        $tenant = $slug ? Tenant::where('slug', $slug)->first() : null;

        if (! $tenant) {
            return redirect()->route('landing')->withErrors(['code' => __('كود المطعم غير موجود')])->withInput();
        }

        return redirect($tenant->url('login'));
    }
}
