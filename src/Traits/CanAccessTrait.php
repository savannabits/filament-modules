<?php

namespace Coolsam\Modules\Traits;

use Nwidart\Modules\Facades\Module;

trait CanAccessTrait
{
    public static function getCurrentModuleName(): string
    {
        $provider = static::class;
        $provider = explode('\\', $provider);
        $provider = strtolower($provider[1]);

        return $provider;
    }

    public static function canAccess(): bool
    {
        $isModuleEnabled = Module::find(
            static::getCurrentModuleName()
        )->isEnabled();
        $parentClass = get_parent_class(static::class);
        $parentAccess = true;

        if (
            is_string($parentClass)
            && str_starts_with($parentClass, 'Filament\\')
            && method_exists($parentClass, 'canAccess')
        ) {
            $parentAccess = $parentClass::canAccess();
        }

        if ($isModuleEnabled && $parentAccess) {
            return true;
        }

        return false;
    }
}
