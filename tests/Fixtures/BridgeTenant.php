<?php

declare(strict_types=1);

namespace Oomycache\Filament\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class BridgeTenant extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
