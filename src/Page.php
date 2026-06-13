<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;

abstract class Page extends \Filament\Pages\Page
{
    use CanAccessTrait;
}
