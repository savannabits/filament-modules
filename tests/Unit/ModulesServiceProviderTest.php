<?php

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\ModulesServiceProvider;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Facades\Module;
use Spatie\LaravelPackageTools\Package;

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

test('modules service provider exposes internal asset and route configuration', function () {
    $provider = $this->app->getProvider(ModulesServiceProvider::class);

    $getAssetPackageName = new ReflectionMethod($provider, 'getAssetPackageName');
    $getAssetPackageName->setAccessible(true);
    expect($getAssetPackageName->invoke($provider))->toBe('coolsam/modules');

    foreach (['getAssets', 'getIcons', 'getRoutes', 'getScriptData', 'getMigrations'] as $methodName) {
        $method = new ReflectionMethod($provider, $methodName);
        $method->setAccessible(true);

        expect($method->invoke($provider))->toBe([]);
    }
});

test('modules service provider publishes module stubs when running in console', function () {
    $stubsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'stubs';
    $stubFile = $stubsPath . DIRECTORY_SEPARATOR . 'coverage-publish.stub';

    file_put_contents($stubFile, 'coverage stub');

    try {
        expect(app()->runningInConsole())->toBeTrue();

        $provider = $this->app->getProvider(ModulesServiceProvider::class);
        $packageBooted = new ReflectionMethod($provider, 'packageBooted');
        $packageBooted->setAccessible(true);
        $packageBooted->invoke($provider);

        expect(array_key_exists(
            realpath($stubFile),
            ServiceProvider::pathsToPublish(ModulesServiceProvider::class, 'modules-stubs') ?? [],
        ))->toBeTrue();
    } finally {
        unlink($stubFile);
    }
});

test('modules install command publishes config and completes successfully', function () {
    $configPath = config_path('filament-modules.php');

    if (file_exists($configPath)) {
        unlink($configPath);
    }

    $this->artisan('modules:install')
        ->assertSuccessful();

    expect(file_exists($configPath))->toBeTrue();
});

test('modules service provider configurePackage registers optional package directories when present', function () {
    $packageRoot = dirname(__DIR__, 2);
    $migrationsPath = $packageRoot . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    $viewsPath = $packageRoot . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';

    foreach ([$migrationsPath, $viewsPath] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    try {
        $provider = $this->app->getProvider(ModulesServiceProvider::class);
        $package = (new Package('filament-modules'))
            ->setBasePath($packageRoot . DIRECTORY_SEPARATOR . 'src');
        $configurePackage = new ReflectionMethod($provider, 'configurePackage');
        $configurePackage->setAccessible(true);
        $configurePackage->invoke($provider, $package);

        expect($package->hasViews)->toBeTrue();
        expect($package->migrationFileNames)->toBe([]);
    } finally {
        foreach ([$viewsPath, $migrationsPath] as $path) {
            if (is_dir($path)) {
                rmdir($path);
            }
        }
    }
});

test('modules service provider skips providers that do not match the module class prefix', function () {
    $module = $this->createTestModule('Blog', enabled: true);
    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'SharedServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    require_once $providerPath;

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->attemptToRegisterModuleProviders();

    expect(collect($this->app->getProviders('Modules\\Blog\\Providers\\SharedServiceProvider')))->toBeEmpty();
});

test('modules service provider auto discover panels skips missing panel classes', function () {
    $module = $this->createTestModule('Blog', enabled: true);
    $providerDir = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'Filament');

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerDir . DIRECTORY_SEPARATOR . 'MissingPanelProvider.php', '<?php // missing class');

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->autoDiscoverPanels();

    expect(class_exists('Modules\\Blog\\Providers\\Filament\\MissingPanelProvider', false))->toBeFalse();

    $this->app->make('filament');
});
