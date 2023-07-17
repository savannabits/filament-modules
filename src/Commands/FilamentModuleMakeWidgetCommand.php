<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Commands\MakeWidgetCommand;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class FilamentModuleMakeWidgetCommand extends MakeWidgetCommand
{
    use ModuleCommandTrait;

    protected $description = 'Creates a Filament widget class.';

    protected $signature = 'module:make-filament-widget {module?} {name?} {--R|resource=} {--C|chart} {--T|table} {--S|stats-overview} {--F|force}';

    protected ?Module $module;

    public function handle(): int
    {
        $module = (string) Str::of($this->argument('module') ?? $this->askRequired('Module Name (e.g. `sales`)', 'module'));
        $this->module = app('modules')->findOrFail($this->getModuleName());
        $path = module_path($module, 'Filament/Widgets/');
        $resourcePath = module_path($module, 'Filament/Resources/');
        $namespace = $this->getModuleNamespace().'\\Filament\\Widgets';
        $resourceNamespace = $this->getModuleNamespace().'\\Filament\\Resources';

        $widget = (string) Str::of($this->argument('name') ?? $this->askRequired('Name (e.g. `BlogPostsChart`)', 'name'))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $widgetClass = (string) Str::of($widget)->afterLast('\\');
        $widgetNamespace = Str::of($widget)->contains('\\') ?
            (string) Str::of($widget)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;

        $resourceInput = $this->option('resource') ?? $this->ask('(Optional) Resource (e.g. `BlogPostResource`)');

        if ($resourceInput !== null) {
            $resource = (string) Str::of($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\');

            if (! Str::of($resource)->endsWith('Resource')) {
                $resource .= 'Resource';
            }

            $resourceClass = (string) Str::of($resource)
                ->afterLast('\\');
        }

        $view = Str::of($widget)->prepend(
            (string) Str::of($resource === null ? "{$namespace}\\" : "{$resourceNamespace}\\{$resource}\\widgets\\")
                ->replace($this->getModuleNamespace(), '')
        )
            ->replace('\\', '/')
            ->replaceFirst('/', '')
            ->explode('/')
            ->map(fn ($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.');

        $path = (string) Str::of($widget)
            ->prepend('/')
            ->prepend($resource === null ? $path : "{$resourcePath}\\{$resource}\\Widgets\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = module_path($module, 'Resources/'.(string) Str::of($view)
            ->replace('.', '/')
            ->prepend('views/')
            ->append('.blade.php'));

        if (! $this->option('force') && $this->checkForCollision([
            $path,
            ($this->option('stats-overview') || $this->option('chart')) ?: $viewPath,
        ])) {
            return static::INVALID;
        }

        if ($this->option('chart')) {
            $chart = $this->choice(
                'Chart type',
                [
                    'Bar chart',
                    'Bubble chart',
                    'Doughnut chart',
                    'Line chart',
                    'Pie chart',
                    'Polar area chart',
                    'Radar chart',
                    'Scatter chart',
                ],
            );

            $this->copyStubToApp('ChartWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'chart' => Str::studly($chart),
            ]);
        } elseif ($this->option('table')) {
            $this->copyStubToApp('TableWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } elseif ($this->option('stats-overview')) {
            $this->copyStubToApp('StatsOverviewWidget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
            ]);
        } else {
            $this->copyStubToApp('Widget', $path, [
                'class' => $widgetClass,
                'namespace' => filled($resource) ? "{$resourceNamespace}\\{$resource}\\Widgets".($widgetNamespace !== '' ? "\\{$widgetNamespace}" : '') : $namespace.($widgetNamespace !== '' ? "\\{$widgetNamespace}" : ''),
                'view' => $this->module->getLowerName().'::'.$view,
            ]);

            $this->copyStubToApp('WidgetView', $viewPath);
        }

        $this->info("Successfully created {$widget}!");

        if ($resource !== null) {
            $this->info("Make sure to register the widget in `{$resourceClass}::getWidgets()`, and then again in `getHeaderWidgets()` or `getFooterWidgets()` of any `{$resourceClass}` page.");
        }

        return static::SUCCESS;
    }

    public function getModuleNamespace()
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }
}
