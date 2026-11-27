<?php

namespace App\Support;

use App\Models\Gym;
use Closure;

/**
 * Holds the gym (tenant) of the current request.
 *
 * Set by the panel tenant middleware, the API middleware and the print routes.
 * While a gym is set, every model using BelongsToGym is filtered to it and new
 * records are stamped with it. With no gym set (console, super-admin panel)
 * queries are not filtered.
 */
class Tenancy
{
    private static ?Gym $gym = null;

    public static function set(?Gym $gym): void
    {
        self::$gym = $gym;
    }

    public static function gym(): ?Gym
    {
        return self::$gym;
    }

    public static function id(): ?int
    {
        return self::$gym?->getKey();
    }

    public static function check(): bool
    {
        return self::$gym !== null;
    }

    /** Run a callback as a given gym, restoring the previous one afterwards. */
    public static function run(?Gym $gym, Closure $callback): mixed
    {
        $previous = self::$gym;
        self::$gym = $gym;

        try {
            return $callback();
        } finally {
            self::$gym = $previous;
        }
    }
}
