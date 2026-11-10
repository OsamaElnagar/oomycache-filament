<?php

declare(strict_types=1);

namespace Oomycache\Filament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Oomycache\Oomycache\Concerns\HasQueryCache;

final class BridgeCacheableRecord extends Model
{
    use HasQueryCache;

    public $timestamps = false;

    protected $guarded = [];
}
