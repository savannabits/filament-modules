<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Commands\MakePageCommand;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class FilamentModuleMakePageCommand extends MakePageCommand
{
    use ModuleCommandTrait;

    protected $description = 'Creates a Filament page class and view.';

    protected $signature = 'module:make-filament-page {name} {context?}  {module?} {--R|resource=} {--T|type=} {--F|force}';

    protected ?Module $module;

    public function handle(): int
    {
        $context = Str::of($this->argument('context') ?? 'Filament')->studly()->toString();
        $module = $this->argument('module') ?: app('modules')->getUsedNow();
        if (!$module) {
            $module = (string) Str::of($this->askRequired('Module Name (e.g. `Sales`)', 'module'));
        }
        $this->module = app('modules')->findOrFail($this->getModuleName());
        $path = module_path($module, "$context/Pages/");
        $resourcePath = module_path($module, "$context/Resources/");
        $namespace = $this->getModuleNamespace()."\\$context\\Pages";
        $resourceNamespace = $this->getModuleNamespace()."\\$context\\Resources";

        $page = (string) Str::of($this->argument('name') ?? $this->askRequired('Name (e.g. `Settings`)', 'name'))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $pageClass = (string) Str::of($page)->afterLast('\\');
        $pageNamespace = Str::of($page)->contains('\\') ?
            (string) Str::of($page)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $resourceInput = $this->option('resource') ?? $this->ask('(Optional) Resource (e.g. `UserResource`)');

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

            $resourcePage = $this->option('type') ?? $this->choice(
                'Which type of page would you like to create?',
                [
                    'custom' => 'Custom',
                    'ListRecords' => 'List',
                    'CreateRecord' => 'Create',
                    'EditRecord' => 'Edit',
                    'ViewRecord' => 'View',
                    'ManageRecords' => 'Manage',
                ],
                'custom',
            );
        }

        $view = Str::of($page)
            ->prepend(
                (string) Str::of($resource === null ? "{$namespace}\\" : "{$resourceNamespace}\\{$resource}\\pages\\")
                    ->replace($this->getModuleNamespace(), '')
            )
            ->replace('\\', '/')
            ->replaceFirst('/', '')
            ->explode('/')
            ->map(fn ($segment) => Str::lower(Str::kebab($segment)))
            ->implode('.');

        $path = (string) Str::of($page)
            ->prepend('/')
            ->prepend($resource === null ? $path : "{$resourcePath}\\{$resource}\\Pages\\")
            ->replace('\\', '/')
            ->replace('//', '/')
            ->append('.php');

        $viewPath = module_path($module, 'Resources/'.(string) Str::of($view)
            ->replace('.', '/')
            ->prepend('views/')
            ->append('.blade.php'));

        $files = array_merge(
            [$path],
            $resourcePage === 'custom' ? [$viewPath] : [],
        );

        if (! $this->option('force') && $this->checkForCollision($files)) {
            return static::INVALID;
        }

        if ($resource === null) {
            $this->copyStubToApp('Page', $path, [
                'class' => $pageClass,
                'namespace' => $namespace.($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'view' => $this->module->getLowerName().'::'.$view,
            ]);
        } else {
            $this->copyStubToApp($resourcePage === 'custom' ? 'CustomResourcePage' : 'ResourcePage', $path, [
                'baseResourcePage' => "{{$context}}\\Resources\\Pages\\".($resourcePage === 'custom' ? 'Page' : $resourcePage),
                'baseResourcePageClass' => $resourcePage === 'custom' ? 'Page' : $resourcePage,
                'namespace' => "{$resourceNamespace}\\{$resource}\\Pages".($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'resource' => "{$resourceNamespace}\\{$resource}",
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'view' => $this->module->getLowerName().'::'.$view,
            ]);
        }

        if ($resource === null || $resourcePage === 'custom') {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->info("Successfully created {$page}!");

        if ($resource !== null) {
            $this->info("Make sure to register the page in `{$resourceClass}::getPages()`.");
        }

        return static::SUCCESS;
    }

    public function getModuleNamespace()
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }
}
