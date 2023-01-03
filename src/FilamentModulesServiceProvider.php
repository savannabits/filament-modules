<?php

namespace Savannabits\FilamentModules;

use Filament\PluginServiceProvider;
use Livewire\Livewire;
use Savannabits\FilamentModules\Commands\FilamentGuardCommand;
use Savannabits\FilamentModules\Commands\FilamentModuleCommand;
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
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasCommands([
                FilamentModuleCommand::class,
                FilamentGuardCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->extend('filament', function ($service, $app) {
            return new FilamentModules($service);
        });
    }

    /**
     * @return void
     */
    public function packageBooted(): void
    {
        Livewire::addPersistentMiddleware([
            ApplyContext::class,
        ]);
    }
}
