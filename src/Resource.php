<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;
use Filament\Resources\Resource as FilamentResource;

abstract class Resource extends FilamentResource
{
    use CanAccessTrait;
}
