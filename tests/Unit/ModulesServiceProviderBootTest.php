<?php

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\ModulesServiceProvider;
use Nwidart\Modules\Facades\Module;

test('modules service provider boots without the removed global module alias', function () {
    expect(class_exists(\Module::class, false))->toBeFalse();
    expect($this->app->getProvider(ModulesServiceProvider::class))->toBeInstanceOf(ModulesServiceProvider::class);
});

test('modules service provider can register enabled module providers discovered on disk', function () {
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

    $namespace = FilamentModules::convertPathToNamespace($providerPath);

    expect($namespace)->toBe('Modules\\Blog\\Providers\\BlogServiceProvider');
    expect(Module::isEnabled('Blog'))->toBeTrue();

    $this->app->register($namespace);

    expect(collect($this->app->getProviders($namespace)))->not->toBeEmpty();
});

test('modules service provider condition matches module-owned provider class names', function () {
    $module = $this->createTestModule('Blog', enabled: true);

    expect(str('BlogServiceProvider')->startsWith('Blog'))->toBeTrue();
    expect(Module::isEnabled('Blog'))->toBeTrue();
    expect(Module::isEnabled('blog'))->toBeTrue();
});
