<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;

class ModulesPlugin implements Plugin
{
    public function getId(): string
    {
        return 'modules';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->topNavigation(config('filament-modules.clusters.enabled', false) && config('filament-modules.clusters.use-top-navigation', false));
        $mode = ConfigMode::tryFrom(config('filament-modules.mode', ConfigMode::BOTH->value));
        if ($mode?->shouldRegisterPlugins()) {
            $plugins = $this->getModulePlugins();
            foreach ($plugins as $modulePlugin) {
                $panel->plugin($modulePlugin::make());
            }
        }
    }

    public function boot(Panel $panel): void
    {
        // Register panels
        $mode = ConfigMode::tryFrom(config('filament-modules.mode', ConfigMode::BOTH->value));
        if ($mode?->shouldRegisterPanels()) {
            $group = config('filament-modules.panels.group', 'Modules');
            $groupIcon = config('filament-modules.panels.group-icon', \Filament\Support\Icons\Heroicon::OutlinedRectangleStack);
            $groupSort = config('filament-modules.panels.group-sort', 0);
            $openInNewTab = config('filament-modules.panels.open-in-new-tab', false);

            $panels = $this->getModulePanels();
            $panel->navigationGroups([
                NavigationGroup::make($group)
                    ->icon($groupIcon)
                    ->collapsed(),
            ]);
            $navItems = collect($panels)->map(function (Panel $panel) use ($group, $groupSort, $openInNewTab) {
                $moduleName = str($panel->getPath())->before('/');
                $module = \Module::find($moduleName);
                if (! $module) {
                    return null;
                }
                //                $panelLabel = str($panel->getId())->after($moduleName)->trim('-')->snake()->title()->replace('_', ' ');
                //                $label = str($module->getTitle())->append(" - ")->append($panelLabel);
                $label = $panel->getBrandName() ?? str($panel->getId())->after($moduleName)->trim('-')->studly()->snake()->replace('_', ' ')->toString();

                return NavigationItem::make($label)
                    ->group($group)
                    ->sort($groupSort)
                    ->url($panel->getUrl())
                    ->openUrlInNewTab($openInNewTab);
            })->toArray();
            $panel->navigationItems($navItems);
        }
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    protected function getModulePlugins(): array
    {
        if (! config('filament-modules.auto-register-plugins', false)) {
            return [];
        }
        // get a glob of all Filament plugins
        $basePath = str(config('modules.paths.modules', 'Modules'));
        $appFolder = str(config('modules.paths.app_folder', 'app'));
        $pattern = $basePath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $appFolder . DIRECTORY_SEPARATOR . 'Filament' . DIRECTORY_SEPARATOR . '*Plugin.php';
        $pluginPaths = glob($pattern);

        return collect($pluginPaths)->map(fn ($path) => FilamentModules::convertPathToNamespace($path))->toArray();

    }

    /**
     * Get all Filament panels registered by modules.
     *
     * @return Panel[]
     */
    protected function getModulePanels(): array
    {
        // get a glob of all Filament panels
        $basePath = str(config('modules.paths.modules', 'Modules'));
        $appFolder = str(config('modules.paths.app_folder', 'app'));
        $pattern = $basePath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $appFolder . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'Filament' . DIRECTORY_SEPARATOR . '*.php';
        $panelPaths = glob($pattern);

        $panelIds = collect($panelPaths)->map(fn ($path) => FilamentModules::convertPathToNamespace($path))->map(function ($class) {
            // Get the panel ID and check if it is registered
            $id = str($class)->afterLast('\\')->before('PanelProvider')->kebab()->lower();
            // get module it belongs to as well
            $moduleName = str($class)->after('Modules\\')->before('\\Providers\\Filament');
            $module = \Module::find($moduleName);
            if (! $module) {
                return null;
            }

            return str($id)->prepend('-')->prepend($module->getKebabName());
        });

        return collect(filament()->getPanels())->filter(function ($panel) use ($panelIds) {
            // Check if the panel ID is in the list of panel IDs
            return $panelIds->contains($panel->getId());
        })->values()->all();

    }
}
