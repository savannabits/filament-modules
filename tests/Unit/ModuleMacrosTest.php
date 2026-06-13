<?php

use Coolsam\Modules\Facades\FilamentModules;
use Nwidart\Modules\Facades\Module;

test('module macros expose package path helpers', function () {
    $module = $this->createTestModule('Blog');

    expect($module->namespace(''))->toBe('Modules\\Blog\\');
    expect($module->getTitle())->toBe('Blog');
    expect($module->appNamespace('Filament\\Resources'))->toBe('Modules\\Blog\\Filament\\Resources');
    expect($module->appPath('Filament'))->toEndWith('Blog' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Filament');
    expect($module->databasePath('migrations'))->toEndWith('Blog' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations');
    expect($module->resourcesPath('views'))->toEndWith('Blog' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views');
});

test('module facade can resolve a scanned module without the global alias', function () {
    expect(class_exists(\Module::class, false))->toBeFalse();

    $this->createTestModule('Blog');

    expect(Module::find('Blog'))->not->toBeNull();
    expect(Module::isEnabled('Blog'))->toBeTrue();
});

test('filament modules helper can resolve module panels path via macros', function () {
    $this->createTestModule('Blog');

    $panels = FilamentModules::getModulePanels('Blog');

    expect($panels)->toBeArray();
});
