<?php

namespace Tests;

use App\Models\Gym;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\GymOnboarding;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        Tenancy::set(null);
        parent::tearDown();
    }

    /** Create a gym with an owner (admin) and make it the current tenant. */
    protected function createGym(string $slug = 'test-gym', array $plan = []): Gym
    {
        $owner = User::create(['name' => 'Owner '.$slug, 'email' => "owner@{$slug}.test", 'password' => 'password']);
        $saasPlan = SaasPlan::create(array_merge(['name' => 'Plan '.$slug, 'price_monthly' => 100, 'price_yearly' => 1000], $plan));
        $gym = GymOnboarding::create($owner, ['name' => 'Gym '.$slug, 'slug' => $slug, 'saas_plan_id' => $saasPlan->id]);
        Tenancy::set($gym);

        return $gym;
    }
}
