<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * قاعدة بيانات منفصلة لكل مطعم:
 * - initialize(): يحول الاتصال الافتراضي لقاعدة بيانات المطعم
 * - provision(): ينشئ قاعدة البيانات ويشغل الـ migrations ويضيف صاحب المطعم
 */
class TenantManager
{
    public const CONNECTION = 'tenant';

    private ?Tenant $current = null;

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function centralConnection(): string
    {
        return config('tenancy.central_connection');
    }

    public function initialize(Tenant $tenant): void
    {
        if ($this->current?->is($tenant) && DB::getDefaultConnection() === self::CONNECTION) {
            return;
        }

        config(['database.connections.'.self::CONNECTION => $this->connectionConfig($tenant)]);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);

        $this->current = $tenant;
        app()->instance(Tenant::class, $tenant);

        // كل route('...') جوه المطعم ياخد الـ slug تلقائياً
        URL::defaults(['tenant' => $tenant->slug]);
        // Filament بيبني بعض الروابط من مسار اللوحة مباشرة (من غير route)
        $this->setPanelPath('app/'.$tenant->slug);

        // إعدادات المطعم مكان config('pos.*')
        config([
            'pos.restaurant_name' => $tenant->name,
            'pos.currency' => $tenant->setting('currency'),
            'pos.tax_rate' => (float) $tenant->setting('tax_rate'),
            'pos.service_rate' => (float) $tenant->setting('service_rate'),
            'pos.restaurant_phone' => $tenant->setting('restaurant_phone'),
            'pos.restaurant_address' => $tenant->setting('restaurant_address'),
            'pos.receipt_footer' => $tenant->setting('receipt_footer'),
            'pos.map_lat' => (float) $tenant->setting('map_lat'),
            'pos.map_lng' => (float) $tenant->setting('map_lng'),
        ]);
    }

    public function end(): void
    {
        DB::setDefaultConnection($this->centralConnection());
        DB::purge(self::CONNECTION);
        $this->current = null;
        app()->forgetInstance(Tenant::class);
        URL::defaults(['tenant' => null]);
        $this->setPanelPath('app/{tenant}');
    }

    private function setPanelPath(string $path): void
    {
        if (class_exists(\Filament\Facades\Filament::class)) {
            rescue(fn () => \Filament\Facades\Filament::getPanel('app', isStrict: false)?->path($path), report: false);
        }
    }

    /** ينفذ كود جوه مطعم معين ويرجع للحالة السابقة */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->current;
        $this->initialize($tenant);
        try {
            return $callback($tenant);
        } finally {
            $previous ? $this->initialize($previous) : $this->end();
        }
    }

    public function connectionConfig(Tenant $tenant): array
    {
        $base = config('database.connections.'.$this->centralConnection());

        if ($base['driver'] === 'sqlite') {
            return [...$base, 'database' => $this->sqlitePath($tenant)];
        }

        return [...$base, 'database' => $tenant->database];
    }

    public static function databaseNameFor(string $slug): string
    {
        return config('tenancy.database_prefix').str_replace('-', '_', Str::slug($slug));
    }

    private function sqlitePath(Tenant $tenant): string
    {
        return rtrim(config('tenancy.sqlite_path'), '/').'/'.$tenant->database.'.sqlite';
    }

    private function driver(): string
    {
        return config('database.connections.'.$this->centralConnection().'.driver');
    }

    /**
     * اتصال منفصل لأوامر CREATE/DROP DATABASE
     * (في MySQL الأوامر دي بتعمل commit لأي transaction مفتوح على نفس الاتصال)
     */
    private function admin(): \Illuminate\Database\Connection
    {
        if (! config('database.connections.tenancy_admin')) {
            config(['database.connections.tenancy_admin' => config('database.connections.'.$this->centralConnection())]);
        }

        return DB::connection('tenancy_admin');
    }

    public function createDatabase(Tenant $tenant): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $tenant->database)) {
            throw new RuntimeException('Invalid tenant database name');
        }

        if ($this->driver() === 'sqlite') {
            $path = $this->sqlitePath($tenant);
            @mkdir(dirname($path), 0775, true);
            if (! file_exists($path)) {
                touch($path);
            }

            return;
        }

        $this->admin()->statement(
            "CREATE DATABASE IF NOT EXISTS `{$tenant->database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }

    public function dropDatabase(Tenant $tenant): void
    {
        if ($this->current?->is($tenant)) {
            $this->end();
        }
        DB::purge(self::CONNECTION);

        if ($this->driver() === 'sqlite') {
            @unlink($this->sqlitePath($tenant));

            return;
        }

        if (! preg_match('/^[a-z0-9_]+$/', $tenant->database)) {
            throw new RuntimeException('Invalid tenant database name');
        }
        $this->admin()->statement("DROP DATABASE IF EXISTS `{$tenant->database}`");
    }

    public function migrate(Tenant $tenant): void
    {
        $this->run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => self::CONNECTION,
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });
    }

    /**
     * مطعم جديد: قاعدة بيانات + جداول + حساب صاحب المطعم (مدير) + منيو تجريبي اختياري
     */
    public function provision(Tenant $tenant, string $ownerPassword, bool $withDemoData = true): void
    {
        $this->createDatabase($tenant);
        $this->migrate($tenant);

        $this->run($tenant, function (Tenant $tenant) use ($ownerPassword, $withDemoData) {
            (new \Database\Seeders\TenantSeeder)->run(
                ownerName: $tenant->owner_name,
                ownerEmail: $tenant->owner_email,
                ownerPassword: $ownerPassword,
                withDemoData: $withDemoData,
            );
        });
    }
}
