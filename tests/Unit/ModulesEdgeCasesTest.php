<?php

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\Modules;
use Coolsam\Modules\ModulesPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

test('find module name for path returns null outside modules directory', function () {
    expect(FilamentModules::findModuleNameForPath('/tmp/not-a-module/provider.php'))->toBeNull();
});

test('exec command writes output when no console command is provided', function () {
    ob_start();

    app(Modules::class)->execCommand('echo uncovered-output');

    $output = ob_get_clean();

    expect(trim($output))->toBe('uncovered-output');
});

test('modules plugin register attaches discovered module plugins', function () {
    config()->set('filament-modules.mode', 'both');
    config()->set('filament-modules.auto-register-plugins', true);

    $module = $this->createTestModule('Blog');
    $pluginDir = $module->appPath('Filament');

    if (! is_dir($pluginDir)) {
        mkdir($pluginDir, 0755, true);
    }

    file_put_contents($pluginDir . DIRECTORY_SEPARATOR . 'BlogAccessPlugin.php', <<<'PHP'
<?php

namespace Modules\Blog\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class BlogAccessPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Blog';
    }

    public function getId(): string
    {
        return 'blog-access';
    }

    public function boot(Panel $panel): void
    {
    }
}
PHP);

    require_once $pluginDir . DIRECTORY_SEPARATOR . 'BlogAccessPlugin.php';

    $panel = Panel::make()->id('admin')->path('admin');
    $plugin = new ModulesPlugin;
    $plugin->register($panel);

    expect($panel->hasPlugin('blog-access'))->toBeTrue();
});

test('modules plugin static helpers resolve plugin instance from panel', function () {
    $this->createTestModule('Blog');

    $pluginClass = new class implements Plugin
    {
        use ModuleFilamentPlugin;

        public function getModuleName(): string
        {
            return 'Blog';
        }

        public function getId(): string
        {
            return 'anonymous-module-plugin';
        }

        public function boot(Panel $panel): void {}
    };

    $panel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin')->plugin($pluginClass::make()),
    );

    filament()->setCurrentPanel($panel);

    expect($pluginClass::make())->toBeInstanceOf($pluginClass::class);
    expect($pluginClass::get())->toBeInstanceOf($pluginClass::class);
});

test('modules plugin skips navigation item when module cannot be resolved from panel path', function () {
    config()->set('filament-modules.mode', 'panels');

    $this->registerTestPanel(
        Panel::make()->id('orphan-admin')->path('orphan/admin')->brandName('Orphan Admin'),
    );

    $adminPanel = $this->registerTestPanel(
        Panel::make()->id('admin')->path('admin'),
    );

    $plugin = new ModulesPlugin;
    $plugin->boot($adminPanel);

    expect(collect($adminPanel->getNavigationItems())->map->getLabel()->contains('Orphan Admin'))->toBeFalse();
});
