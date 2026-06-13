<?php

use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\Facades\FilamentModules;

test('config mode controls panel registration', function () {
    expect(ConfigMode::PANELS->shouldRegisterPanels())->toBeTrue();
    expect(ConfigMode::PANELS->shouldRegisterPlugins())->toBeFalse();

    expect(ConfigMode::PLUGINS->shouldRegisterPanels())->toBeFalse();
    expect(ConfigMode::PLUGINS->shouldRegisterPlugins())->toBeTrue();

    expect(ConfigMode::BOTH->shouldRegisterPanels())->toBeTrue();
    expect(ConfigMode::BOTH->shouldRegisterPlugins())->toBeTrue();
});

test('config mode can be resolved from config values', function () {
    config()->set('filament-modules.mode', ConfigMode::PLUGINS->value);

    expect(FilamentModules::getMode())->toBe(ConfigMode::PLUGINS);
});
