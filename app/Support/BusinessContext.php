<?php

namespace App\Support;

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
}
