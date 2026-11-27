<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Tenancy\TenantManager;
use Illuminate\Console\Command;
use Throwable;

/** تشغيل migrations قواعد بيانات المطاعم (بعد كل تحديث للنظام) */
class TenantsMigrate extends Command
{
    protected $signature = 'tenants:migrate {--tenant=* : كود مطعم معين (ممكن أكتر من واحد)}';

    protected $description = 'Run tenant migrations on every restaurant database';

    public function handle(TenantManager $manager): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))
            ->orderBy('id')->get();

        $failed = 0;
        foreach ($tenants as $tenant) {
            try {
                $manager->createDatabase($tenant); // لو اتمسحت بالغلط
                $manager->migrate($tenant);
                $this->line("✔ {$tenant->slug}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("✘ {$tenant->slug}: {$e->getMessage()}");
            }
        }

        $this->info("Migrated {$tenants->count()} restaurant(s), {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
