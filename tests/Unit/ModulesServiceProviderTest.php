<?php

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\ModulesServiceProvider;
use Nwidart\Modules\Facades\Module;

test('modules service provider auto discovers module panel providers before filament resolves', function () {
    $module = $this->createModulePanelProvider('Blog', 'BlogAdminPanelProvider');

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->autoDiscoverPanels();

    $this->app->make('filament');

    expect(class_exists('Modules\\Blog\\Providers\\Filament\\BlogAdminPanelProvider'))->toBeTrue();
    expect($module->isEnabled())->toBeTrue();
});

test('modules service provider skips disabled module providers', function () {
    $module = $this->createTestModule('Blog', enabled: false);

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'BlogServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    require_once $providerPath;

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->attemptToRegisterModuleProviders();

    expect(collect($this->app->getProviders('Modules\\Blog\\Providers\\BlogServiceProvider')))->toBeEmpty();
});

test('modules service provider registers enabled module providers from disk', function () {
    $module = $this->createTestModule('Blog', enabled: true);

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'BlogServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    require_once $providerPath;

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->attemptToRegisterModuleProviders();

    expect(collect($this->app->getProviders('Modules\\Blog\\Providers\\BlogServiceProvider')))->not->toBeEmpty();
});

test('modules service provider resolves custom namespace providers', function () {
    $module = $this->createTestModule('CustomProviderModule', enabled: true);

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'CustomServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace MyCompany\Modules\Custom\Providers;

use Illuminate\Support\ServiceProvider;

class CustomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    require_once $providerPath;

    $namespace = FilamentModules::resolveProviderClass($providerPath);

    expect(FilamentModules::findModuleNameForPath($providerPath))->toBe('CustomProviderModule');
    expect($namespace)->toBe('MyCompany\\Modules\\Custom\\Providers\\CustomServiceProvider');

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->attemptToRegisterModuleProviders();

    expect(collect($this->app->getProviders($namespace)))->toBeEmpty();
});

test('module macros resolve path helpers for nested folders', function () {
    $module = $this->createTestModule('Blog');

    expect($module->migrationsPath('2024'))->toEndWith('database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2024');
    expect($module->seedersPath())->toEndWith('database' . DIRECTORY_SEPARATOR . 'seeders');
    expect($module->factoriesPath())->toEndWith('database' . DIRECTORY_SEPARATOR . 'factories');
    expect(Module::find('Blog'))->not->toBeNull();
});
