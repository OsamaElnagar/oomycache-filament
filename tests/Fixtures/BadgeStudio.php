<?php

declare(strict_types=1);

namespace Oomycache\Filament\Tests\Fixtures;

use Oomycache\Filament\Pages\QueryCacheStudio;

/**
 * Studio subclass with deterministic status/model data for badge tests.
 */
final class BadgeStudio extends QueryCacheStudio
{
    /**
     * @var array<string, mixed>
     */
    public static array $status = [];

    /**
     * @var array<string, array{table: string, token: string, ttl: int, enabled: bool}>
     */
    public static array $models = [];

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return self::$status;
    }

    /**
     * @return array<string, array{table: string, token: string, ttl: int, enabled: bool}>
     */
    public function cacheableModels(): array
    {
        return self::$models;
    }
}
