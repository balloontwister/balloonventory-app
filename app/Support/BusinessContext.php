<?php

namespace App\Support;

use Closure;

/**
 * Holds the "current business" id that BusinessScope uses to tenant-scope
 * queries. Set per web request by the SetBusinessContext middleware.
 *
 * IMPORTANT — this is process-global static state. Under PHP-FPM each request
 * is an isolated process, so the value resets naturally between requests. It is
 * NOT automatically reset inside long-running contexts that have no
 * SetBusinessContext middleware:
 *
 *   - queued jobs / listeners
 *   - console commands
 *   - Octane workers
 *
 * In those contexts a null value makes BusinessScope a no-op (queries span ALL
 * businesses), and a value left over from previous work leaks into the next.
 * Always wrap tenant-scoped work in such contexts with runFor() so the id is
 * set correctly and restored afterwards.
 */
class BusinessContext
{
    protected static ?string $currentId = null;

    public static function set(string $id): void
    {
        static::$currentId = $id;
    }

    public static function currentId(): ?string
    {
        return static::$currentId;
    }

    public static function clear(): void
    {
        static::$currentId = null;
    }

    /**
     * Run a callback with the business context set to $id, restoring the prior
     * value afterwards (even if the callback throws). This is the safe way to
     * scope tenant queries outside a web request — queued jobs, console
     * commands, Octane tasks — where the middleware never runs.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function runFor(?string $id, Closure $callback): mixed
    {
        $previous = static::$currentId;
        static::$currentId = $id;

        try {
            return $callback();
        } finally {
            static::$currentId = $previous;
        }
    }
}
