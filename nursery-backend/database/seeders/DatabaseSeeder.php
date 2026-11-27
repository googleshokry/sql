<?php

namespace Database\Seeders;

use App\Actions\OnboardNursery;
use App\Models\Plan;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles are global definitions; assignments are scoped per nursery (teams).
        foreach (Roles::ALL as $role) {
            Role::findOrCreate($role, 'web');
        }

        User::updateOrCreate(['email' => 'admin@platform.test'], [
            'name' => 'Platform Admin',
            'password' => 'password',
            'is_super_admin' => true,
        ])->forceFill(['is_super_admin' => true])->save();

        if (! app()->isProduction()) {
            $this->demo();
        }
    }

    private function demo(): void
    {
        $plan = Plan::firstOrCreate(['price_monthly' => 500], [
            'name' => ['ar' => 'الأساسية', 'en' => 'Basic'],
            'max_children' => 100,
            'max_branches' => 2,
        ]);

        $onboard = app(OnboardNursery::class);

        foreach ([['stars', 'النجوم', 'Stars'], ['sunshine', 'الشمس', 'Sunshine']] as [$slug, $ar, $en]) {
            if (\App\Models\Nursery::where('slug', $slug)->exists()) {
                continue;
            }

            $nursery = $onboard(
                ['name' => ['ar' => "حضانة {$ar}", 'en' => "{$en} Nursery"], 'slug' => $slug, 'plan_id' => $plan->id],
                ['name' => "{$en} Admin", 'email' => "admin@{$slug}.test", 'password' => 'password', 'phone' => null],
            );

            $teacher = User::create([
                'name' => "{$en} Teacher", 'email' => "teacher@{$slug}.test",
                'phone' => $slug === 'stars' ? '01000000001' : '01000000002', 'password' => 'password',
            ]);
            $nursery->members()->attach($teacher);
            $onboard->assignRole($nursery, $teacher, Roles::TEACHER);
        }
    }
}
