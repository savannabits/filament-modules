<?php
/*
 *          M""""""""`M            dP
 *          Mmmmmm   .M            88
 *          MMMMP  .MMM  dP    dP  88  .dP   .d8888b.
 *          MMP  .MMMMM  88    88  88888"    88'  `88
 *          M' .MMMMMMM  88.  .88  88  `8b.  88.  .88
 *          M         M  `88888P'  dP   `YP  `88888P'
 *          MMMMMMMMMMM    -*-  Created by Zuko  -*-
 *
 *          * * * * * * * * * * * * * * * * * * * * *
 *          * -    - -   F.R.E.E.M.I.N.D   - -    - *
 *          * -  Copyright © 2026 (Z) Programing  - *
 *          *    -  -  All Rights Reserved  -  -    *
 *          * * * * * * * * * * * * * * * * * * * * *
 */

namespace Coolsam\Modules;

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\Support\NamespaceResolver;
use Coolsam\Modules\Testing\TestsModules;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportTesting\Testable;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Nwidart\Modules\Module as NwidartModule;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ModulesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'modules';

    public static string $viewNamespace = 'modules';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->endWith(function (InstallCommand $command) {
                        $command->askToStarRepoOnGitHub('coolsam726/filament-modules');
                    });
            });

        $configFileName = 'filament-modules';

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile($configFileName);
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(NamespaceResolver::class);
        $this->app->singleton(Modules::class);
        $this->registerModuleMacros();
        $this->autoDiscoverPanels();
    }

    public function attemptToRegisterModuleProviders(): void
    {
        // It is necessary to register them here to avoid late registration (after Panels have already been booted)
        $modulesPath = (string) config('modules.paths.modules', 'Modules');
        $appFolder = (string) config('modules.paths.app_folder', 'app');

        $providers = [];

        // Both layouts are supported: with an app folder (`modules/Blog/app/Providers`)
        // and without one (`modules/Blog/Providers`).
        foreach ([[$appFolder], []] as $appSegment) {
            foreach ([[], ['Filament']] as $filamentSegment) {
                $segments = array_merge([$modulesPath, '*'], $appSegment, ['Providers'], $filamentSegment, ['*Provider.php']);
                $providers = array_merge($providers, FilamentModules::globFiles(...$segments));
            }
        }

        foreach (array_unique($providers) as $provider) {
            $moduleName = FilamentModules::findModuleNameForPath($provider);
            $module = $moduleName ? ModuleFacade::find($moduleName) : null;

            if (! $module || ! $module->isEnabled()) {
                continue;
            }

            $namespace = FilamentModules::resolveProviderClass($provider);
            $className = str($namespace)->afterLast('\\')->toString();

            if (! str($className)->startsWith($module->getStudlyName())) {
                continue;
            }

            if (class_exists($namespace) && is_subclass_of($namespace, ServiceProvider::class)) {
                $this->app->register($namespace);
            }
        }
    }

    public function autoDiscoverPanels(): void
    {
        $this->app->beforeResolving('filament', function () {
            $panels = collect(ModuleFacade::allEnabled())->flatMap(function (NwidartModule $module) {
                $panelProviders = FilamentModules::globFiles($module->appPath('Providers/Filament'), '*.php');

                return collect($panelProviders)
                    ->map(fn ($path) => FilamentModules::resolveProviderClass($path))
                    ->all();
            })->filter()->unique();

            foreach ($panels as $panel) {
                if (class_exists($panel) && is_subclass_of($panel, ServiceProvider::class)) {
                    $this->app->register($panel);
                }
            }
        });
    }

    public function packageBooted(): void
    {
        $this->attemptToRegisterModuleProviders();
        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/modules/{$file->getFilename()}"),
                ], 'modules-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsModules);
    }

    protected function getAssetPackageName(): ?string
    {
        return 'coolsam/modules';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            Commands\ModuleFilamentInstallCommand::class,
            Commands\ModuleMakeFilamentClusterCommand::class,
            Commands\ModuleMakeFilamentPluginCommand::class,
            Commands\ModuleMakeFilamentResourceCommand::class,
            Commands\ModuleMakeFilamentPageCommand::class,
            Commands\ModuleMakeFilamentWidgetCommand::class,
            Commands\ModuleMakeFilamentThemeCommand::class,
            Commands\ModuleMakeFilamentPanelCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getMigrations(): array
    {
        return [
            //            'create_modules_table',
        ];
    }

    protected function registerModuleMacros(): void
    {
        // The base namespace is read from what the module actually declares
        // (Composer PSR-4 map, then its composer.json, then its sources), so
        // modules that do not live under `config('modules.namespace')` resolve
        // correctly. See https://github.com/coolsam726/filament-modules/issues/154
        NwidartModule::macro('namespace', function (?string $relativeNamespace = '') {
            return FilamentModules::getModuleNamespace($this, $relativeNamespace ?? '');
        });

        NwidartModule::macro('getTitle', function () {
            return str($this->getStudlyName())->kebab()->title()->replace('-', ' ')->toString();
        });

        NwidartModule::macro('appNamespace', function (string $relativeNamespace = '') {
            $prefix = str(config('modules.paths.app_folder', 'app'))->ltrim(DIRECTORY_SEPARATOR, '\\')->studly()->toString();
            $relativeNamespace = trim($relativeNamespace, '\\');
            if (filled($prefix)) {
                $relativeNamespace = str_replace($prefix . '\\', '', $relativeNamespace);
                $relativeNamespace = str_replace($prefix, '', $relativeNamespace);
            }

            return $this->namespace($relativeNamespace);
        });
        NwidartModule::macro('appPath', function (string $relativePath = '') {
            $appPath = $this->getExtraPath(config('modules.paths.app_folder', 'app'));

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });

        NwidartModule::macro('databasePath', function (string $relativePath = '') {
            $appPath = $this->getExtraPath('database');

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });

        NwidartModule::macro('resourcesPath', function (string $relativePath = '') {
            $appPath = $this->getExtraPath('resources');

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });

        NwidartModule::macro('migrationsPath', function (string $relativePath = '') {
            $appPath = $this->databasePath('migrations');

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });

        NwidartModule::macro('seedersPath', function (string $relativePath = '') {
            $appPath = $this->databasePath('seeders');

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });

        NwidartModule::macro('factoriesPath', function (string $relativePath = '') {
            $appPath = $this->databasePath('factories');

            return str($appPath . ($relativePath ? DIRECTORY_SEPARATOR . $relativePath : ''))
                ->replace(['/', '\\'], DIRECTORY_SEPARATOR)
                ->replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR)
                ->toString();
        });
    }
}
