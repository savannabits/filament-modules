<?php

use Coolsam\Modules\Contracts\ModuleActivator;
use Coolsam\Modules\Contracts\ModuleDefinition;
use Coolsam\Modules\Contracts\ModuleRegistry;
use Coolsam\Modules\Dependencies\ModuleDependencyResolver;
use Coolsam\Modules\Exceptions\ModuleIsDependedUponException;
use Coolsam\Modules\Exceptions\UnsatisfiedDependenciesException;

function makeModuleDefinition(string $name, array $dependencies = []): ModuleDefinition
{
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
}

test('activation order resolves dependencies first', function () {
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('find')->with('Core')->andReturn(makeModuleDefinition('Core'));
    $registry->shouldReceive('find')->with('Blog')->andReturn(makeModuleDefinition('Blog', ['Core']));
    $registry->shouldReceive('find')->with('Shop')->andReturn(makeModuleDefinition('Shop', ['Blog']));

    $resolver = new ModuleDependencyResolver($registry);

    expect($resolver->activationOrder('Shop'))->toBe(['Core', 'Blog', 'Shop']);
});

test('cannot activate a module with missing dependencies', function () {
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('find')->with('Shop')->andReturn(makeModuleDefinition('Shop', ['Missing']));
    $registry->shouldReceive('find')->with('Missing')->andReturnNull();
    $registry->shouldReceive('exists')->with('Missing')->andReturnFalse();

    $resolver = new ModuleDependencyResolver($registry);

    $resolver->assertCanActivate('Shop');
})->throws(UnsatisfiedDependenciesException::class);

test('cannot deactivate a module with active dependents', function () {
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('find')->with('Blog')->andReturn(makeModuleDefinition('Blog'));
    $registry->shouldReceive('all')->andReturn(collect([
        makeModuleDefinition('Blog'),
        makeModuleDefinition('Shop', ['Blog']),
    ]));

    $activator = Mockery::mock(ModuleActivator::class);
    $activator->shouldReceive('isActive')->with('Blog', null)->andReturnTrue();
    $activator->shouldReceive('isActive')->with(Mockery::type(ModuleDefinition::class), null)->andReturnUsing(
        fn (ModuleDefinition $module) => $module->name() === 'Shop'
    );
    app()->instance(ModuleActivator::class, $activator);

    $resolver = new ModuleDependencyResolver($registry);

    $resolver->assertCanDeactivate('Blog');
})->throws(ModuleIsDependedUponException::class);

test('returns active dependents for a module', function () {
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('find')->with('Blog')->andReturn(makeModuleDefinition('Blog'));
    $registry->shouldReceive('all')->andReturn(collect([
        makeModuleDefinition('Blog'),
        makeModuleDefinition('Shop', ['Blog']),
        makeModuleDefinition('Reports', ['Blog']),
    ]));

    $activator = Mockery::mock(ModuleActivator::class);
    $activator->shouldReceive('isActive')->andReturnUsing(
        fn (ModuleDefinition | string $module) => in_array(
            is_string($module) ? $module : $module->name(),
            ['Shop'],
            true
        )
    );
    app()->instance(ModuleActivator::class, $activator);

    $resolver = new ModuleDependencyResolver($registry);

    expect($resolver->dependents('Blog'))->toBe(['Shop']);
});
