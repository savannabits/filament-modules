<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\Testing\TestsModules;
use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Filesystem\Filesystem;
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
        $this->registerModuleMacros();
        $this->autoDiscoverPanels();
    }

    public function attemptToRegisterModuleProviders(): void
    {
        // It is necessary to register them here to avoid late registration (after Panels have already been booted)
        $pattern1 = config(
            'modules.paths.modules',
            'Modules'
        ) . '/*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . '*Provider.php';
        $pattern2 = config(
            'modules.paths.modules',
            'Modules'
        ) . '/*' . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'Filament' . DIRECTORY_SEPARATOR . '*Provider.php';
        $serviceProviders = glob($pattern1);
        $panelProviders = glob($pattern2);
        $providers = array_merge($serviceProviders, $panelProviders);

        foreach ($providers as $provider) {
            $namespace = FilamentModules::resolveProviderClass($provider);
            $moduleName = FilamentModules::findModuleNameForPath($provider);

            if (! $moduleName || ! ModuleFacade::isEnabled($moduleName)) {
                continue;
            }

            $className = str($namespace)->afterLast('\\')->toString();
            $moduleStudlyName = str($moduleName)->studly()->toString();

            if (str($className)->startsWith($moduleStudlyName) && class_exists($namespace)) {
                $this->app->register($namespace);
            }
        }
    }

    public function autoDiscoverPanels(): void
    {
        $this->app->beforeResolving('filament', function () {
            $modules = ModuleFacade::allEnabled();
            $cacheKey = 'filament-modules-panel-providers';
            $ttl = 10;  // 24 hours
            $modules = ModuleFacade::allEnabled();
            $panels = collect($modules)->flatMap(function (NwidartModule $module) {
                $panelProviders = glob($module->getExtraPath('app/Providers/Filament') . '/*.php');

                return collect($panelProviders)->map(function ($path) {
                    return $this->app[Modules::class]->convertPathToNamespace($path);
                })->toArray();
            })->toArray();
            foreach ($panels as $panel) {
                if (class_exists($panel)) {
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
        NwidartModule::macro('namespace', function (?string $relativeNamespace = '') {
            $relativeNamespace = $relativeNamespace ?? '';
            $base = trim(config('modules.namespace', 'Modules'), '\\');
            $relativeNamespace = trim($relativeNamespace, '\\');
            $studlyName = $this->getStudlyName();

            return str($base)->append('\\')->append($studlyName)->append('\\')->append($relativeNamespace)->replace('\\\\', '\\')->toString();
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
