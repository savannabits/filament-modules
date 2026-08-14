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

use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Nwidart\Modules\Facades\Module as ModuleFacade;

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
            foreach ($this->getModulePlugins() as $modulePlugin) {
                $plugin = method_exists($modulePlugin, 'make') ? $modulePlugin::make() : app($modulePlugin);

                // A module plugin registered explicitly by the panel wins.
                if ($panel->hasPlugin($plugin->getId())) {
                    continue;
                }

                $panel->plugin($plugin);
            }
        }
    }

    public function boot(Panel $panel): void
    {
        // Register panels
        $mode = ConfigMode::tryFrom(config('filament-modules.mode', ConfigMode::BOTH->value));
        if ($mode?->shouldRegisterPanels()) {
            $group = config('filament-modules.panels.group', 'Modules');
            $groupIcon = config('filament-modules.panels.group-icon', Heroicon::OutlinedRectangleStack);
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
                $module = ModuleFacade::find($moduleName);
                if (! $module) {
                    return null;
                }
                //                $panelLabel = str($panel->getId())->after($moduleName)->trim('-')->snake()->title()->replace('_', ' ');
                //                $label = str($module->getTitle())->append(" - ")->append($panelLabel);
                $label = $panel->getBrandName() ?: str($panel->getId())->after($moduleName)->trim('-')->studly()->snake()->replace('_', ' ')->toString();

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

    /**
     * @return array<int, class-string<Plugin>>
     */
    protected function getModulePlugins(): array
    {
        if (! config('filament-modules.auto-register-plugins', false)) {
            return [];
        }

        return collect($this->globModuleFiles('Filament', '*Plugin.php'))
            ->map(fn (string $path) => [
                'class' => FilamentModules::resolveClass($path),
                'module' => FilamentModules::findModuleNameForPath($path),
            ])
            ->filter(function (array $plugin) {
                $module = $plugin['module'] ? ModuleFacade::find($plugin['module']) : null;

                // A plugin whose class cannot be resolved (or whose module is
                // disabled) is skipped instead of crashing the panel.
                return $module?->isEnabled()
                    && filled($plugin['class'])
                    && class_exists($plugin['class'])
                    && is_subclass_of($plugin['class'], Plugin::class);
            })
            ->pluck('class')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Glob inside every module, covering both the app-folder layout
     * (`modules/Blog/app/…`) and the flat one (`modules/Blog/…`).
     *
     * @return array<int, string>
     */
    protected function globModuleFiles(string ...$segments): array
    {
        $modulesPath = (string) config('modules.paths.modules', 'Modules');
        $appFolder = (string) config('modules.paths.app_folder', 'app');

        $paths = array_merge(
            FilamentModules::globFiles(...array_merge([$modulesPath, '*', $appFolder], $segments)),
            FilamentModules::globFiles(...array_merge([$modulesPath, '*'], $segments)),
        );

        return array_values(array_unique($paths));
    }

    /**
     * Get all Filament panels registered by modules.
     *
     * @return Panel[]
     */
    protected function getModulePanels(): array
    {
        // get a glob of all Filament panels
        $panelPaths = $this->globModuleFiles('Providers', 'Filament', '*.php');

        $panelIds = collect($panelPaths)->map(function ($path) {
            $class = FilamentModules::resolveProviderClass($path);

            if (blank($class) || ! class_exists($class)) {
                return null;
            }

            $moduleName = FilamentModules::findModuleNameForPath($path);
            $module = $moduleName ? ModuleFacade::find($moduleName) : null;

            if (! $module) {
                return null;
            }

            $id = str($class)->afterLast('\\')->before('PanelProvider')->kebab()->lower();

            return str($id)->prepend('-')->prepend($module->getKebabName());
        });

        return collect(filament()->getPanels())->filter(function ($panel) use ($panelIds) {
            // Check if the panel ID is in the list of panel IDs
            return $panelIds->contains($panel->getId());
        })->values()->all();

    }
}
