<?php

use Coolsam\Modules\Facades\FilamentModules;

test('can resolve provider class from file namespace declaration', function () {
    $module = $this->createTestModule('CustomNsModule');

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'CustomNamespaceServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace MyCompany\CRM\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class CustomNamespaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    expect(FilamentModules::resolveClassFromProviderFile($providerPath))
        ->toBe('MyCompany\\CRM\\Blog\\Providers\\CustomNamespaceServiceProvider');
    expect(FilamentModules::resolveProviderClass($providerPath))
        ->toBe('MyCompany\\CRM\\Blog\\Providers\\CustomNamespaceServiceProvider');
});

test('can find module name from provider path regardless of namespace', function () {
    $module = $this->createTestModule('PathLookupModule');

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'BlogServiceProvider.php');

    expect(FilamentModules::findModuleNameForPath($providerPath))->toBe('PathLookupModule');
});

test('registers providers that declare custom namespaces when enabled', function () {
    $module = $this->createTestModule('CustomProviderModule', enabled: true);

    $providerPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'BlogServiceProvider.php');
    $providerDir = dirname($providerPath);

    if (! is_dir($providerDir)) {
        mkdir($providerDir, 0755, true);
    }

    file_put_contents($providerPath, <<<'PHP'
<?php

namespace MyCompany\CRM\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
}
PHP);

    $namespace = FilamentModules::resolveProviderClass($providerPath);

    expect(FilamentModules::findModuleNameForPath($providerPath))->toBe('CustomProviderModule');
    expect(str($namespace)->afterLast('\\')->startsWith('Blog'))->toBeTrue();

    require_once $providerPath;

    $this->app->register($namespace);

    expect(collect($this->app->getProviders($namespace)))->not->toBeEmpty();
});
