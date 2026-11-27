<?php

namespace App\Support;

use App\Models\Company;
use Closure;

/**
 * Holds the company (tenant) the current request / job is working for.
 *
 * Inside a request the tenant is the signed-in user's company. Console code, seeders and
 * signed links set it explicitly with {@see Tenancy::run()} or {@see Tenancy::set()}.
 */
class Tenancy
{
    protected static ?int $companyId = null;

    protected static bool $bypassed = false;

    public static function set(Company|int|null $company): void
    {
        static::$companyId = $company instanceof Company ? $company->getKey() : $company;
    }

    public static function forget(): void
    {
        static::$companyId = null;
    }

    public static function id(): ?int
    {
        if (static::$companyId !== null) {
            return static::$companyId;
        }

        return auth()->user()?->company_id;
    }

    /** @var array<int, Company> */
    protected static array $companies = [];

    public static function company(): ?Company
    {
        $id = static::id();
        if (! $id) {
            return null;
        }

        return static::$companies[$id] ??= Company::query()->with('plan')->findOrFail($id);
    }

    /** Clear the per-request company cache (after changing a plan or status). */
    public static function refresh(): void
    {
        static::$companies = [];
    }

    public static function isBypassed(): bool
    {
        return static::$bypassed;
    }

    /**
     * Run a callback as the given company, restoring the previous tenant afterwards.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function run(Company|int $company, Closure $callback): mixed
    {
        $previous = static::$companyId;
        static::set($company);

        try {
            return $callback();
        } finally {
            static::$companyId = $previous;
        }
    }

    /**
     * Run a callback across all companies (platform administration only).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function withoutTenancy(Closure $callback): mixed
    {
        $previous = static::$bypassed;
        static::$bypassed = true;

        try {
            return $callback();
        } finally {
            static::$bypassed = $previous;
        }
    }
}
