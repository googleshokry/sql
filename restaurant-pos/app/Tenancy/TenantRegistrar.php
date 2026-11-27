<?php

namespace App\Tenancy;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

/** تسجيل مطعم جديد: سجل مركزي + قاعدة بيانات + صاحب المطعم */
class TenantRegistrar
{
    public function __construct(private TenantManager $manager) {}

    public static function slugRules(?Tenant $ignore = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
            Rule::notIn(config('tenancy.reserved_slugs')),
            Rule::unique(Tenant::class, 'slug')->ignore($ignore?->id),
        ];
    }

    /**
     * @param  array{name:string, slug:string, owner_name:string, owner_email:string, owner_phone?:?string, password:string, plan_id?:?int, demo?:bool, status?:string, months?:int}  $data
     */
    public function register(array $data): Tenant
    {
        $status = $data['status'] ?? 'trial';
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'database' => TenantManager::databaseNameFor($data['slug']),
            'owner_name' => $data['owner_name'],
            'owner_email' => $data['owner_email'],
            'owner_phone' => $data['owner_phone'] ?? null,
            'plan_id' => $data['plan_id'] ?? Plan::where('is_active', true)->orderBy('sort')->value('id'),
            'status' => $status,
            'trial_ends_at' => $status === 'trial' ? now()->addDays(config('tenancy.trial_days')) : null,
            'subscription_ends_at' => $status === 'active' ? now()->addMonths($data['months'] ?? 1) : null,
        ]);

        try {
            $this->manager->provision($tenant, $data['password'], (bool) ($data['demo'] ?? true));
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed', ['tenant' => $tenant->slug, 'error' => $e->getMessage()]);
            $this->manager->dropDatabase($tenant);
            $tenant->delete();
            throw $e;
        }

        return $tenant;
    }

    /** حذف نهائي: قاعدة البيانات + السجل */
    public function delete(Tenant $tenant): void
    {
        $this->manager->dropDatabase($tenant);
        $tenant->delete();
    }
}
