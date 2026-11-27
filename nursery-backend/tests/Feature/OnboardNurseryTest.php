<?php

use App\Actions\OnboardNursery;
use App\Models\Branch;
use App\Models\User;
use App\Support\Roles;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (Roles::ALL as $role) {
        Role::findOrCreate($role, 'web');
    }
});

it('creates the nursery, its main branch and its admin', function () {
    $nursery = app(OnboardNursery::class)(
        ['name' => ['ar' => 'نجوم', 'en' => 'Stars'], 'slug' => 'stars'],
        ['name' => 'Admin', 'email' => 'admin@stars.test', 'password' => 'password'],
    );

    $admin = User::where('email', 'admin@stars.test')->sole();

    expect($nursery->default_locale)->toBe('ar')
        ->and($admin->locale)->toBe('ar')
        ->and(Branch::withoutGlobalScopes()->where('nursery_id', $nursery->id)->count())->toBe(1)
        ->and($admin->rolesIn($nursery)->all())->toBe([Roles::NURSERY_ADMIN]);
});

it('links an existing user instead of creating a duplicate', function () {
    $existing = User::factory()->create(['email' => 'owner@test.test']);

    $nursery = app(OnboardNursery::class)(
        ['name' => ['ar' => 'شمس', 'en' => 'Sunshine'], 'slug' => 'sunshine', 'default_locale' => 'en'],
        ['name' => 'Owner', 'email' => 'owner@test.test'],
    );

    expect(User::where('email', 'owner@test.test')->count())->toBe(1)
        ->and($nursery->members()->whereKey($existing->id)->exists())->toBeTrue();
});
