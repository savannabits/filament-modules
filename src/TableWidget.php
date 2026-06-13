<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Traits\CanAccessTrait;

abstract class TableWidget extends \Filament\Widgets\TableWidget
{
    use CanAccessTrait;

    public static function canView(): bool
    {
        return self::canAccess();
    }
}
