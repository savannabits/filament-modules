<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;

abstract class StatsOverviewWidget extends \Filament\Widgets\StatsOverviewWidget
{
    use CanAccessTrait;

    public static function canView(): bool
    {
        return self::canAccess();
    }
}
