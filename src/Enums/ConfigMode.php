<?php

namespace Coolsam\Modules\Enums;

enum ConfigMode: string
{
    case PANELS = 'panels';
    case PLUGINS = 'plugins';

    case BOTH = 'both';

    public function shouldRegisterPanels(): bool
    {
        return in_array($this->value, [self::PANELS->value, self::BOTH->value]);
    }

    public function shouldRegisterPlugins(): bool
    {
        return in_array($this->value, [self::PLUGINS->value, self::BOTH->value]);
    }
}
