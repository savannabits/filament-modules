<?php

use Coolsam\Modules\ModulesPlugin;
use Filament\Panel;

test('modules plugin discovers module filament plugins when auto registration is enabled', function () {
    config()->set('filament-modules.auto-register-plugins', true);

    $this->createModuleFilamentPluginFile('Blog');

    $plugin = new ModulesPlugin;
    $method = new ReflectionMethod($plugin, 'getModulePlugins');
    $method->setAccessible(true);

    expect($method->invoke($plugin))->toBe([
        'Modules\\Blog\\Filament\\BlogAccessPlugin',
    ]);
});

test('modules plugin skips plugin discovery when auto registration is disabled', function () {
    config()->set('filament-modules.auto-register-plugins', false);

    $this->createModuleFilamentPluginFile('Blog');

    $plugin = new ModulesPlugin;
    $method = new ReflectionMethod($plugin, 'getModulePlugins');
    $method->setAccessible(true);

    expect($method->invoke($plugin))->toBe([]);
});

test('modules plugin discovers module panels registered in filament', function () {
    $this->createModulePanelProvider('Blog', 'AdminPanelProvider');

    $this->registerTestPanel(
        Panel::make()->id('blog-admin')->path('blog/admin'),
    );

    $plugin = new ModulesPlugin;
    $method = new ReflectionMethod($plugin, 'getModulePanels');
    $method->setAccessible(true);

    $panels = $method->invoke($plugin);

    expect(collect($panels)->map->getId()->all())->toContain('blog-admin');
});

test('modules plugin boot adds navigation for module panels', function () {
    config()->set('filament-modules.mode', 'panels');
    config()->set('filament-modules.panels.group', 'Module Panels');

    $this->createModulePanelProvider('Blog', 'AdminPanelProvider');

    $this->registerTestPanel(
        Panel::make()->id('blog-admin')->path('blog/admin')->brandName('Blog Admin'),
    );

    $adminPanel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin'),
    );

    $plugin = new ModulesPlugin;
    $plugin->boot($adminPanel);

    $navigation = $adminPanel->getNavigationItems();

    expect(collect($navigation)->map->getLabel()->contains('Blog Admin'))->toBeTrue();
});

test('modules plugin register skips plugin registration in panels mode', function () {
    config()->set('filament-modules.mode', 'panels');
    config()->set('filament-modules.auto-register-plugins', true);

    $this->createModuleFilamentPluginFile('Blog');

    $panel = Panel::make()->id('admin')->path('admin');
    $plugin = new ModulesPlugin;
    $plugin->register($panel);

    expect($panel->getPlugins())->toBeEmpty();
});
