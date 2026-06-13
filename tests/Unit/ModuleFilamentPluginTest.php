<?php

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Panel;
use Nwidart\Modules\Facades\Module;

test('module filament plugin skips registration when module is disabled', function () {
    $this->createTestModule('Blog', enabled: false);

    $plugin = new class
    {
        use ModuleFilamentPlugin;

        public function getModuleName(): string
        {
            return 'Blog';
        }

        public function getId(): string
        {
            return 'blog-module-plugin';
        }
    };

    $panel = Panel::make()->id('admin')->path('admin');
    $plugin->register($panel);

    expect(Module::isEnabled('Blog'))->toBeFalse();
});

test('module filament plugin registers discovery paths when module is enabled', function () {
    config()->set('filament-modules.clusters.enabled', true);

    $module = $this->createTestModule('Blog', enabled: true);

    foreach ([
        'Filament/Pages',
        'Filament/Resources',
        'Filament/Widgets',
        'Livewire',
        'Filament/Clusters/Settings',
    ] as $relativePath) {
        $path = $module->appPath(str_replace('/', DIRECTORY_SEPARATOR, $relativePath));

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    $plugin = new class
    {
        use ModuleFilamentPlugin;

        public bool $afterRegisterCalled = false;

        public function getModuleName(): string
        {
            return 'Blog';
        }

        public function getId(): string
        {
            return 'blog-module-plugin';
        }

        public function afterRegister(Panel $panel): void
        {
            $this->afterRegisterCalled = true;
        }
    };

    $panel = Panel::make()->id('admin')->path('admin');
    $plugin->register($panel);

    expect($plugin->afterRegisterCalled)->toBeTrue();
});
