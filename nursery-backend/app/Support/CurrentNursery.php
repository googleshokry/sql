<?php

namespace App\Support;

use App\Models\Nursery;

/**
 * Holds the nursery (tenant) for the current request.
 * Set by Filament tenancy middleware (web) or SetNurseryFromHeader (API).
 */
class CurrentNursery
{
    protected ?Nursery $nursery = null;

    public function set(?Nursery $nursery): void
    {
        $this->nursery = $nursery;

        // Scope spatie/laravel-permission roles to this nursery (teams mode).
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($nursery?->getKey());
    }

    public function get(): ?Nursery
    {
        return $this->nursery;
    }

    public function id(): ?int
    {
        return $this->nursery?->getKey();
    }

    public function check(): bool
    {
        return $this->nursery !== null;
    }

    public function clear(): void
    {
        $this->set(null);
    }
}
