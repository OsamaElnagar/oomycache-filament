<?php

declare(strict_types=1);

namespace Oomycache\Filament\Tests\Fixtures;

use Oomycache\Filament\Support\HasCachedNavigationBadge;

/**
 * Minimal consumer of the reusable cached badge-count trait.
 */
final class BadgeCounter
{
    use HasCachedNavigationBadge;
}
