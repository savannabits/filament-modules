<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;
use Filament\Resources\Resource;

abstract class Resource extends \Filament\Resources\Resource
{
    use CanAccessTrait;
}
