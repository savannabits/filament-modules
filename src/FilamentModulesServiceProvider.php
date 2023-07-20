<?php

namespace Savannabits\FilamentModules;

use Filament\PluginServiceProvider;
use Livewire\Livewire;
use Savannabits\FilamentModules\Commands\FilamentGuardCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleMakePageCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleMakeRelationManagerCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleMakeResourceCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleMakeWidgetCommand;
use Savannabits\FilamentModules\Commands\ShieldGenerateCommand;
use Savannabits\FilamentModules\Http\Middleware\ApplyContext;
use Spatie\LaravelPackageTools\Package;

class FilamentModulesServiceProvider extends PluginServiceProvider
{
    public static string $name = 'filament-modules';

    protected array $resources = [
        // CustomResource::class,
    ];

    protected array $pages = [
        // CustomPage::class,
    ];

    protected array $widgets = [
        // CustomWidget::class,
    ];

    protected array $styles = [
        'plugin-filament-modules' => __DIR__.'/../resources/dist/filament-modules.css',
    ];

    protected array $scripts = [
        'plugin-filament-modules' => __DIR__.'/../resources/dist/filament-modules.js',
    ];

    // protected array $beforeCoreScripts = [
    //     'plugin-filament-modules' => __DIR__ . '/../resources/dist/filament-modules.js',
    // ];

    public function configurePackage(Package $package): void
    {
        $commands = [
            FilamentModuleCommand::class,
            FilamentGuardCommand::class,
            FilamentModuleMakePageCommand::class,
            FilamentModuleMakeRelationManagerCommand::class,
            FilamentModuleMakeResourceCommand::class,
            FilamentModuleMakeWidgetCommand::class,
        ];
        if ($this->app->has('filament-shield')) {
            $commands[] = ShieldGenerateCommand::class;
        }
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasCommands($commands);
    }

    public function packageRegistered(): void
    {
        $this->app->extend('filament', function ($service, $app) {
            return new FilamentModules($service);
        });
        if ($this->app->has('filament-shield')) {
            $this->app->extend('filament-shield', function ($service, $app) {
                return new \Savannabits\FilamentModules\Extensions\FilamentShield();
            });
        }

    }

    public function packageBooted(): void
    {
        parent::packageBooted();
        Livewire::addPersistentMiddleware([
            ApplyContext::class,
        ]);
    }
}
