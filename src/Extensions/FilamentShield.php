<?php

namespace Savannabits\FilamentModules\Extensions;

use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Module;

class FilamentShield extends \BezhanSalleh\FilamentShield\FilamentShield
{
    public function getResources(): ?array
    {
        $resources = static::getAllFilamentResources();

        $return = $resources
            ->unique()
            ->filter(function ($resource) {
                if (Utils::isGeneralExcludeEnabled()) {
                    return ! in_array(
                        Str::of($resource)->afterLast('\\'),
                        Utils::getExcludedResouces()
                    );
                }

                return true;
            })
            ->reduce(function ($resources, $resource) {
                $name = $this->getPermissionIdentifier($resource);

                $resources["{$name}"] = [
                    'resource' => "{$name}",
                    'model' => Str::of($resource::getModel())->afterLast('\\'),
                    'fqcn' => $resource,
                ];

                return $resources;
            }, collect())
            ->sortKeys()
            ->toArray();

        return $return;
    }

    public static function getLocalizedResourceLabel(string $entity): string
    {
        $label = static::getAllFilamentResources()->filter(function ($resource) use ($entity) {
            return $resource === $entity;
        })->first()::getModelLabel();

        return Str::of($label)->headline();
    }

    public static function isModule(string $page): bool
    {
        return Str::of($page)->startsWith('module_');
    }

    public static function getAssociatedModule(string $page): ?\Nwidart\Modules\Module
    {
        if (! static::isModule($page)) {
            return null;
        }
        $name = Str::of($page)->after('module_')->lower();

        return Module::find($name);
    }

    public static function getLocalizedPageLabel(string $page): string|bool
    {
        if (static::isModule($page)) {
            return Str::of(static::getAssociatedModule($page)?->getName() ?? '')->append(' Module')->toString();
        }
        $object = static::transformClassString($page);

        if (Str::of($pageTitle = invade(new $object())->getTitle())->isNotEmpty()) {
            return $pageTitle;
        }

        return invade(new $object())->getNavigationLabel();
    }

    public function getDefaultPermissionIdentifier(string $resource): string
    {
        return Str::of($resource)
            ->replace('Resources\\', '')
            ->before('Resource')
            ->replace('\\', '')
            ->snake()
            ->replace('_', '::');
    }

    protected static function getAllFilamentResources(): Collection
    {
        $resources = collect();
        $contexts = Filament::getContexts();
        foreach ($contexts as $name => $context) {
            Filament::forContext($name, function () use (&$resources) {
                $resources = $resources->merge(collect(Filament::getResources()));
            });
        }

        return $resources;
    }

    protected static function getAllFilamentPages(): Collection
    {
        $items = collect();
        $contexts = Filament::getContexts();
        foreach ($contexts as $name => $context) {
            Filament::forContext($name, function () use (&$items) {
                $items = $items->merge(collect(Filament::getPages()));
            });
        }

        return $items;
    }

    protected static function getAllFilamentWidgets(): Collection
    {
        $items = collect();
        $contexts = Filament::getContexts();
        foreach ($contexts as $name => $context) {
            Filament::forContext($name, function () use (&$items) {
                $items = $items->merge(collect(Filament::getWidgets()));
            });
        }

        return $items;
    }

    public static function getPages(): ?array
    {
        $return = static::getAllFilamentPages()
            ->filter(function ($page) {
                if (Utils::isGeneralExcludeEnabled()) {
                    return ! in_array(Str::afterLast($page, '\\'), Utils::getExcludedPages());
                }

                return true;
            })
            ->reduce(function ($pages, $page) {
                $prepend = Str::of(Utils::getPagePermissionPrefix())->append('_');
                $name = Str::of(class_basename($page))
                    ->prepend($prepend);

                $pages["{$name}"] = "{$name}";

                return $pages;
            }, collect())
            ->merge(collect(Module::all())->map(fn ($module) => Str::of($module->getLowerName())->prepend('module_')->toString())->keyBy(fn ($item) => $item))
            ->toArray();

        return $return;
    }

    public static function getWidgets(): ?array
    {
        return static::getAllFilamentWidgets()
            ->filter(function ($widget) {
                if (Utils::isGeneralExcludeEnabled()) {
                    return ! in_array(Str::afterLast($widget, '\\'), Utils::getExcludedWidgets());
                }

                return true;
            })
            ->reduce(function ($widgets, $widget) {
                $prepend = Str::of(Utils::getWidgetPermissionPrefix())->append('_');
                $name = Str::of(class_basename($widget))
                    ->prepend($prepend);

                $widgets["{$name}"] = "{$name}";

                return $widgets;
            }, collect())
            ->toArray();
    }

    protected static function transformClassString(string $string, bool $isPageClass = true): string
    {
        return (string) ($isPageClass ? static::getAllFilamentPages() : static::getAllFilamentWidgets())
            ->first(fn ($item) => Str::endsWith(
                $item,
                Str::of($string)
                    ->after('_')
                    ->studly()
            ));
    }

    public static function generateForPage(string $page): void
    {
        if (Utils::isPageEntityEnabled()) {
            $permission = Utils::getPermissionModel()::firstOrCreate(
                ['name' => $page],
                ['guard_name' => Utils::getFilamentAuthGuard()]
            )->name;

            static::giveSuperAdminPermission($permission);
        }

        // Additionally, generate for each module:
        static::giveSuperAdminPermission($permission);
    }
}
