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

test('modules plugin exposes its identifier', function () {
    expect((new ModulesPlugin)->getId())->toBe('modules');
});

test('modules plugin make and get helpers resolve the plugin from the panel', function () {
    $panel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin')->plugin(ModulesPlugin::make()),
    );

    filament()->setCurrentPanel($panel);

    expect(ModulesPlugin::make())->toBeInstanceOf(ModulesPlugin::class);
    expect(ModulesPlugin::get())->toBeInstanceOf(ModulesPlugin::class);
});

test('modules plugin register enables top navigation when cluster config requests it', function () {
    config()->set('filament-modules.clusters.enabled', true);
    config()->set('filament-modules.clusters.use-top-navigation', true);
    config()->set('filament-modules.mode', 'panels');

    $panel = Panel::make()->id('admin')->path('admin');
    (new ModulesPlugin)->register($panel);

    expect($panel->hasTopNavigation())->toBeTrue();
});

test('modules plugin boot derives navigation labels from panel ids when brand name is missing', function () {
    config()->set('filament-modules.mode', 'panels');

    $this->createModulePanelProvider('Blog', 'AdminPanelProvider');

    $this->registerTestPanel(
        Panel::make()->id('blog-admin')->path('blog/admin')->brandName(''),
    );

    $adminPanel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin'),
    );

    (new ModulesPlugin)->boot($adminPanel);

    expect(collect($adminPanel->getNavigationItems())->map->getLabel()->contains('admin'))->toBeTrue();
});

test('modules plugin boot skips navigation items when module cannot be resolved from panel path', function () {
    config()->set('filament-modules.mode', 'panels');

    $this->createModulePanelProvider('Blog', 'AdminPanelProvider');

    $this->registerTestPanel(
        Panel::make()->id('blog-admin')->path('missing/admin')->brandName('Blog Admin'),
    );

    $adminPanel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin'),
    );

    (new ModulesPlugin)->boot($adminPanel);

    expect(collect($adminPanel->getNavigationItems())->filter()->map->getLabel()->contains('Blog Admin'))->toBeFalse();
});

test('modules plugin get module panels skips invalid provider classes and missing modules', function () {
    $module = $this->createModulePanelProvider('Blog', 'AdminPanelProvider');
    $providerDir = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'Filament');

    file_put_contents($providerDir . DIRECTORY_SEPARATOR . 'InvalidPanelProvider.php', '<?php // missing class');

    $moduleJson = $this->modulesPath() . DIRECTORY_SEPARATOR . 'Blog' . DIRECTORY_SEPARATOR . 'module.json';
    unlink($moduleJson);

    $plugin = new ModulesPlugin;
    $method = new ReflectionMethod($plugin, 'getModulePanels');
    $method->setAccessible(true);

    expect($method->invoke($plugin))->toBe([]);
});
