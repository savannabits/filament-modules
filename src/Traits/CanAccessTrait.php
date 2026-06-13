<?php

namespace Coolsam\Modules\Traits;

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
        $isModuleEnabled = app(\Coolsam\Modules\Contracts\ModuleActivator::class)->isActive(
            static::getCurrentModuleName()
        );
        $parentAccess = function_exists('canAccess') ? parent::canAccess() : true;

        if ($isModuleEnabled && $parentAccess) {
            return true;
        }

        return false;
    }
}
