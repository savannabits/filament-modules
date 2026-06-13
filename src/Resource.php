<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;

abstract class Resource extends \Filament\Resources\Resource
{
    use CanAccessTrait;
}
