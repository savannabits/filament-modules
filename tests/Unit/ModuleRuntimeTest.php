<?php

use Coolsam\Modules\Contracts\ModuleActivator;
use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Coolsam\Modules\Contracts\TenantContext;
use Coolsam\Modules\Dependencies\ModuleDependencyResolver;
use Coolsam\Modules\Drivers\Nwidart\NwidartModuleDefinition;
use Coolsam\Modules\Facades\ModuleActivator as ModuleActivatorFacade;
use Coolsam\Modules\Facades\ModuleRegistry as ModuleRegistryFacade;
use Coolsam\Modules\ModulesServiceProvider;
use Coolsam\Modules\Support\DefaultTenantContext;
use Illuminate\Support\Collection;
use Nwidart\Modules\Facades\Module;

test('nwidart module registry lists and resolves module definitions', function () {
    $this->createTestModule('Blog', enabled: true);

    $registry = app(ModuleRegistry::class);

    expect($registry->exists('Blog'))->toBeTrue();
    expect($registry->exists('Missing'))->toBeFalse();
    expect($registry->find('Missing'))->toBeNull();

    $definition = $registry->find('Blog');

    expect($definition)->toBeInstanceOf(NwidartModuleDefinition::class);
    expect($registry->all())->toHaveCount(1);
    expect($registry->all()->first()->name())->toBe('Blog');
});

test('module registry and activator facades proxy to the container bindings', function () {
    $this->createTestModule('Blog', enabled: true);

    expect(ModuleRegistryFacade::exists('Blog'))->toBeTrue();
    expect(ModuleRegistryFacade::find('Blog'))->not->toBeNull();
    expect(ModuleRegistryFacade::all())->toHaveCount(1);
    expect(ModuleActivatorFacade::isActive('Blog'))->toBeTrue();
    expect(ModuleActivatorFacade::active())->toHaveCount(1);
});

test('nwidart module definition exposes manifest metadata and dependencies', function () {
    $module = $this->createTestModule('Shop', enabled: true);
    $modulePath = $module->getPath();

    file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'Shop',
        'alias' => 'shop',
        'description' => 'Shop module',
        'keywords' => ['commerce'],
        'priority' => 1,
        'providers' => [],
        'files' => [],
        'depends' => ['Blog'],
    ], JSON_THROW_ON_ERROR));

    $this->clearModuleRepositoryCache();

    $definition = app(ModuleRegistry::class)->find('Shop');

    expect($definition)->not->toBeNull();
    expect($definition->name())->toBe('Shop');
    expect($definition->alias())->toBe('shop');
    expect($definition->path())->toBe($module->getPath());
    expect($definition->dependencies())->toBe(['Blog']);
    expect($definition->manifest())->toMatchArray([
        'name' => 'Shop',
        'depends' => ['Blog'],
    ]);
    expect($definition->module()->getName())->toBe('Shop');
});

test('nwidart module definition returns empty dependencies for invalid manifest values', function () {
    $module = $this->createTestModule('Reports', enabled: true);
    $modulePath = $module->getPath();

    file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'Reports',
        'alias' => 'reports',
        'requires' => 'invalid',
    ], JSON_THROW_ON_ERROR));

    $this->clearModuleRepositoryCache();

    $definition = app(ModuleRegistry::class)->find('Reports');

    expect($definition?->dependencies())->toBe([]);
});

test('file module activator reports enabled and disabled modules', function () {
    $this->createTestModule('Blog', enabled: true);
    $this->createTestModule('Archive', enabled: false);

    $activator = app(ModuleActivator::class);
    $blogDefinition = app(ModuleRegistry::class)->find('Blog');

    expect($activator->isActive('Blog'))->toBeTrue();
    expect($activator->isActive($blogDefinition))->toBeTrue();
    expect($activator->isActive('Archive'))->toBeFalse();
    expect($activator->active()->map->name()->all())->toBe(['Blog']);
});

test('file module activator enables dependencies in activation order', function () {
    $blogPath = $this->modulesPath() . DIRECTORY_SEPARATOR . 'Blog';
    $shopPath = $this->modulesPath() . DIRECTORY_SEPARATOR . 'Shop';

    foreach ([$blogPath, $shopPath] as $path) {
        if (! is_dir($path)) {
            mkdir($path . DIRECTORY_SEPARATOR . 'app', 0755, true);
        }
    }

    file_put_contents($blogPath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'Blog',
        'alias' => 'blog',
        'providers' => [],
        'files' => [],
    ], JSON_THROW_ON_ERROR));

    file_put_contents($shopPath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'Shop',
        'alias' => 'shop',
        'providers' => [],
        'files' => [],
        'requires' => ['Blog'],
    ], JSON_THROW_ON_ERROR));

    $this->clearModuleRepositoryCache();

    Module::find('Blog')?->disable();
    Module::find('Shop')?->disable();

    app(ModuleActivator::class)->activate('Shop');

    expect(Module::isEnabled('Blog'))->toBeTrue();
    expect(Module::isEnabled('Shop'))->toBeTrue();
});

test('file module activator can deactivate a module', function () {
    $this->createTestModule('Blog', enabled: true);

    app(ModuleActivator::class)->deactivate('Blog');

    expect(Module::isEnabled('Blog'))->toBeFalse();
});

test('file module activator rejects per-tenant operations when tenancy is enabled', function () {
    $this->createTestModule('Blog', enabled: true);

    config(['filament-modules.tenancy.enabled' => true]);

    $activator = app(ModuleActivator::class);

    expect(fn () => $activator->isActive('Blog', 1))->toThrow(RuntimeException::class);
    expect(fn () => $activator->activate('Blog', 1))->toThrow(RuntimeException::class);
    expect(fn () => $activator->deactivate('Blog', 1))->toThrow(RuntimeException::class);
});

test('file module activator throws when activating an unknown module', function () {
    expect(fn () => app(ModuleActivator::class)->activate('Missing'))
        ->toThrow(RuntimeException::class, 'Module [Missing] was not found.');
});

test('module dependency resolver handles revisits and missing modules', function () {
    $makeDefinition = function (string $name, array $dependencies = []): ModuleDefinition {
        return new class($name, $dependencies) implements ModuleDefinition
        {
            public function __construct(
                private string $name,
                private array $dependencies,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function alias(): string
            {
                return strtolower($this->name);
            }

            public function path(): string
            {
                return '/modules/' . $this->name;
            }

            public function dependencies(): array
            {
                return $this->dependencies;
            }

            public function manifest(): array
            {
                return ['name' => $this->name, 'requires' => $this->dependencies];
            }
        };
    };

    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('find')->with('A')->andReturn($makeDefinition('A', ['B']));
    $registry->shouldReceive('find')->with('B')->andReturn($makeDefinition('B', ['A']));
    $registry->shouldReceive('find')->with('Missing')->andReturnNull();

    $resolver = new ModuleDependencyResolver($registry);

    expect($resolver->activationOrder('A'))->toContain('A', 'B');
    expect(fn () => $resolver->activationOrder('Missing'))
        ->toThrow(RuntimeException::class, 'Module [Missing] was not found.');
});

test('modules service provider registers custom tenant context from config', function () {
    $contextClass = new class implements TenantContext
    {
        public function resolve(): string | int | null
        {
            return 'tenant-1';
        }
    };

    config(['filament-modules.tenancy.context' => $contextClass::class]);

    $this->app->forgetInstance(TenantContext::class);

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $registerModuleRuntime = new ReflectionMethod($provider, 'registerModuleRuntime');
    $registerModuleRuntime->setAccessible(true);
    $registerModuleRuntime->invoke($provider);

    expect(app(TenantContext::class))->toBeInstanceOf($contextClass::class);
    expect(app(TenantContext::class)->resolve())->toBe('tenant-1');
});

test('modules service provider rejects unsupported activation drivers', function () {
    config(['filament-modules.activation.driver' => 'database']);

    $this->app->forgetInstance(ModuleActivator::class);

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $registerModuleRuntime = new ReflectionMethod($provider, 'registerModuleRuntime');
    $registerModuleRuntime->setAccessible(true);
    $registerModuleRuntime->invoke($provider);

    expect(fn () => app(ModuleActivator::class))
        ->toThrow(InvalidArgumentException::class, 'Unsupported module activation driver [database].');
});

test('modules service provider auto discover panels skips registry modules missing from nwidart', function () {
    $definition = Mockery::mock(ModuleDefinition::class);
    $definition->shouldReceive('name')->andReturn('Ghost');

    app()->instance(ModuleRegistry::class, new class($definition) implements ModuleRegistry
    {
        public function __construct(private ModuleDefinition $definition) {}

        public function all(): Collection
        {
            return collect([$this->definition]);
        }

        public function find(string $name): ?ModuleDefinition
        {
            return $name === 'Ghost' ? $this->definition : null;
        }

        public function exists(string $name): bool
        {
            return $name === 'Ghost';
        }
    });

    app()->instance(ModuleActivator::class, Mockery::mock(ModuleActivator::class, function ($mock): void {
        $mock->shouldReceive('isActive')->andReturn(true);
    }));

    $provider = $this->app->getProvider(ModulesServiceProvider::class);
    $provider->autoDiscoverPanels();

    $this->app->make('filament');

    expect(Module::find('Ghost'))->toBeNull();
});

test('default tenant context resolves to null', function () {
    expect((new DefaultTenantContext)->resolve())->toBeNull();
});
