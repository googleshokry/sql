<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Models\Gym;
use App\Models\Plan;
use App\Models\SaasPlan;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

/** Creates a new gym (tenant) with its owner and sensible starter data. */
class GymOnboarding
{
    public static function create(User $owner, array $data): Gym
    {
        return DB::transaction(function () use ($owner, $data) {
            $gym = Gym::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'currency' => $data['currency'] ?? 'ج.م',
                'locale' => $data['locale'] ?? app()->getLocale(),
                'saas_plan_id' => $data['saas_plan_id'] ?? SaasPlan::where('is_active', true)->orderBy('sort')->value('id'),
                'billing_cycle' => $data['billing_cycle'] ?? 'monthly',
                'owner_id' => $owner->id,
            ]);

            $gym->users()->attach($owner->id, ['role' => User::ROLE_ADMIN]);

            Tenancy::run($gym, function () {
                foreach ([
                    ['name' => __('شهر'), 'duration_days' => 30, 'price' => 400, 'freeze_days' => 7, 'guest_invitations' => 1, 'color' => '#22c55e'],
                    ['name' => __('3 شهور'), 'duration_days' => 90, 'price' => 1000, 'freeze_days' => 15, 'guest_invitations' => 3, 'color' => '#3b82f6'],
                    ['name' => __('6 شهور'), 'duration_days' => 180, 'price' => 1800, 'freeze_days' => 30, 'guest_invitations' => 5, 'color' => '#8b5cf6'],
                    ['name' => __('سنة'), 'duration_days' => 365, 'price' => 3000, 'freeze_days' => 60, 'guest_invitations' => 10, 'color' => '#f59e0b'],
                    ['name' => __('12 حصة'), 'duration_days' => 30, 'price' => 250, 'sessions_count' => 12, 'color' => '#06b6d4'],
                ] as $plan) {
                    Plan::create($plan);
                }
                foreach (['إيجار', 'كهرباء ومياه', 'مرتبات', 'صيانة أجهزة', 'نظافة', 'دعاية وإعلان', 'أخرى'] as $category) {
                    ExpenseCategory::create(['name' => __($category)]);
                }
            });

            return $gym;
        });
    }

    /** Whether the user may open another gym/branch under their current plan. */
    public static function canAddBranch(User $user): bool
    {
        $owned = Gym::where('owner_id', $user->id)->with('saasPlan')->get();
        if ($owned->isEmpty()) {
            return true;
        }
        $allowed = $owned->max(fn (Gym $g) => $g->saasPlan?->max_branches ?? 1);

        return $owned->count() < $allowed;
    }
}
