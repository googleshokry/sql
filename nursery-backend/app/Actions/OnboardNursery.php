<?php

namespace App\Actions;

use App\Models\Nursery;
use App\Models\User;
use App\Support\CurrentNursery;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a nursery, its main branch, and its first nursery_admin in one transaction.
 * An existing user (same email) is linked instead of duplicated.
 */
class OnboardNursery
{
    public function __invoke(array $nurseryData, array $admin): Nursery
    {
        return DB::transaction(function () use ($nurseryData, $admin) {
            $nursery = Nursery::create($nurseryData);

            $nursery->branches()->create([
                'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main branch'],
            ]);

            $user = User::where('email', $admin['email'])->first();

            if (! $user) {
                if (blank($admin['password'] ?? null)) {
                    throw ValidationException::withMessages(['admin.password' => __('validation.required', ['attribute' => __('app.password')])]);
                }

                $user = User::create([
                    'name' => $admin['name'],
                    'email' => $admin['email'],
                    'phone' => $admin['phone'] ?? null,
                    'password' => $admin['password'],
                    'locale' => $nursery->default_locale ?? config('app.locale'),
                ]);
            }

            $nursery->members()->syncWithoutDetaching([$user->id]);

            $this->assignRole($nursery, $user, Roles::NURSERY_ADMIN);

            return $nursery;
        });
    }

    public function assignRole(Nursery $nursery, User $user, string $role): void
    {
        $current = app(CurrentNursery::class);
        $previous = $current->get();

        $current->set($nursery);
        $user->unsetRelation('roles');
        $user->assignRole($role);

        $current->set($previous);
        $user->unsetRelation('roles');
    }
}
