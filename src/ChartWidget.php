<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;

abstract class ChartWidget extends \Filament\Widgets\ChartWidget
{
    use CanAccessTrait;

    public static function canView(): bool
    {
        return self::canAccess();
    }
}
