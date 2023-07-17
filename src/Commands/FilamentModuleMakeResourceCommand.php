<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Commands\MakeResourceCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class FilamentModuleMakeResourceCommand extends MakeResourceCommand
{
    use ModuleCommandTrait;

    protected $description = 'Creates a Filament resource class and default page classes.';

    protected $signature = 'module:make-filament-resource {name?} {context?} {module?} {--soft-deletes} {--view} {--G|generate} {--S|simple} {--F|force}';

    protected ?Module $module;

    public function handle(): int
    {
        $context = Str::of($this->argument('context') ?? 'Filament')->studly()->toString();
        $module = $this->argument('module') ?: app('modules')->getUsedNow();
        if (! $module) {
            $module = (string) Str::of($this->askRequired('Module Name (e.g. `Sales`)', 'module'));
        }
        $this->module = app('modules')->findOrFail($this->getModuleName());

        $path = module_path($module, "$context/Resources/");
        $namespace = $this->getModuleNamespace()."\\$context\\Resources";

        $model = Str::of($this->argument('name') ?? $this->askRequired('Model (e.g. `BlogPost`)', 'name'))
            ->studly()
            ->beforeLast('Resource')
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->studly()
            ->replace('/', '\\')->toString();

        if (blank($model)) {
            $model = 'Resource';
        }

        $modelClass = (string) Str::of($model)->afterLast('\\');
        $modelNamespace = Str::of($model)->contains('\\') ?
            (string) Str::of($model)->beforeLast('\\') :
            $this->getModuleNamespace().'\\Entities';
        $pluralModelClass = (string) Str::of($modelClass)->pluralStudly();

        $resource = "{$modelClass}Resource";
        $resourceClass = "{$modelClass}Resource";
        $resourceNamespace = $namespace.($resourceClass !== '' ? "\\{$resourceClass}" : '');
        $listResourcePageClass = "List{$pluralModelClass}";
        $manageResourcePageClass = "Manage{$pluralModelClass}";
        $createResourcePageClass = "Create{$modelClass}";
        $editResourcePageClass = "Edit{$modelClass}";
        $viewResourcePageClass = "View{$modelClass}";

        $baseResourcePath =
            (string) Str::of($resource)
                ->prepend('/')
                ->prepend($path)
                ->replace('\\', '/')
                ->replace('//', '/');

        $resourcePath = "{$baseResourcePath}.php";
        $resourcePagesDirectory = "{$baseResourcePath}/Pages";
        $listResourcePagePath = "{$resourcePagesDirectory}/{$listResourcePageClass}.php";
        $manageResourcePagePath = "{$resourcePagesDirectory}/{$manageResourcePageClass}.php";
        $createResourcePagePath = "{$resourcePagesDirectory}/{$createResourcePageClass}.php";
        $editResourcePagePath = "{$resourcePagesDirectory}/{$editResourcePageClass}.php";
        $viewResourcePagePath = "{$resourcePagesDirectory}/{$viewResourcePageClass}.php";

        if (! $this->option('force') && $this->checkForCollision([
            $resourcePath,
            $listResourcePagePath,
            $manageResourcePagePath,
            $createResourcePagePath,
            $editResourcePagePath,
            $viewResourcePagePath,
        ])) {
            return static::INVALID;
        }

        $pages = '';
        $pages .= '\'index\' => Pages\\'.($this->option('simple') ? $manageResourcePageClass : $listResourcePageClass).'::route(\'/\'),';

        if (! $this->option('simple')) {
            $pages .= PHP_EOL."'create' => Pages\\{$createResourcePageClass}::route('/create'),";

            if ($this->option('view')) {
                $pages .= PHP_EOL."'view' => Pages\\{$viewResourcePageClass}::route('/{record}'),";
            }

            $pages .= PHP_EOL."'edit' => Pages\\{$editResourcePageClass}::route('/{record}/edit'),";
        }

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        $relations = '';

        if ($this->option('simple')) {
            $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
                $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
            }
        } else {
            $relations .= PHP_EOL.'public static function getRelations(): array';
            $relations .= PHP_EOL.'{';
            $relations .= PHP_EOL.'    return [';
            $relations .= PHP_EOL.'        //';
            $relations .= PHP_EOL.'    ];';
            $relations .= PHP_EOL.'}'.PHP_EOL;
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $eloquentQuery = '';

        if ($this->option('soft-deletes')) {
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';

            $eloquentQuery .= PHP_EOL.PHP_EOL.'public static function getEloquentQuery(): Builder';
            $eloquentQuery .= PHP_EOL.'{';
            $eloquentQuery .= PHP_EOL.'    return parent::getEloquentQuery()';
            $eloquentQuery .= PHP_EOL.'        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL.'            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL.'        ]);';
            $eloquentQuery .= PHP_EOL.'}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $this->copyStubToApp('Resource', $resourcePath, [
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'formSchema' => $this->indentString($this->option('generate') ? $this->getResourceFormSchema(
                "$modelNamespace\\$model",
            ) : '//', 4),
            'modelNamespace' => $modelNamespace,
            'resourceSlug' => Str::slug($pluralModelClass),
            'model' => $model === 'Resource' ? 'Resource as ResourceModel' : $model,
            'modelClass' => $model === 'Resource' ? 'ResourceModel' : $modelClass,
            'namespace' => $namespace,
            'pages' => $this->indentString($pages, 3),
            'relations' => $this->indentString($relations, 1),
            'resource' => "{$namespace}\\{$resourceClass}",
            'resourceClass' => $resourceClass,
            'resourceNamespace' => $resourceNamespace,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 4),
            'tableColumns' => $this->indentString($this->option('generate') ? $this->getResourceTableColumns(
                "$modelNamespace\\$model",
            ) : '//', 4),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make(),' : '//',
                4,
            ),
        ]);

        if ($this->option('simple')) {
            $this->copyStubToApp('ResourceManagePage', $manageResourcePagePath, [
                'namespace' => "{$namespace}\\{$resourceClass}\\Pages",
                'resource' => "{$namespace}\\{$resourceClass}",
                'resourceClass' => $resourceClass,
                'resourceNamespace' => $resourceNamespace,
                'resourcePageClass' => $manageResourcePageClass,
            ]);
        } else {
            $this->copyStubToApp('ResourceListPage', $listResourcePagePath, [
                'namespace' => "{$resourceNamespace}\\Pages",
                'resource' => $resource,
                'resourceNamespace' => $resourceNamespace,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $listResourcePageClass,
            ]);

            $this->copyStubToApp('ResourcePage', $createResourcePagePath, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\CreateRecord',
                'baseResourcePageClass' => 'CreateRecord',
                'namespace' => "{$resourceNamespace}\\Pages",
                'resource' => $resource,
                'resourceNamespace' => $resourceNamespace,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $createResourcePageClass,
            ]);

            $editPageActions = [];

            if ($this->option('view')) {
                $this->copyStubToApp('ResourceViewPage', $viewResourcePagePath, [
                    'namespace' => "{$resourceNamespace}\\Pages",
                    'resource' => $resource,
                    'resourceNamespace' => $resourceNamespace,
                    'resourceClass' => $resourceClass,
                    'resourcePageClass' => $viewResourcePageClass,
                ]);

                $editPageActions[] = 'Actions\ViewAction::make(),';
            }

            $editPageActions[] = 'Actions\DeleteAction::make(),';

            if ($this->option('soft-deletes')) {
                $editPageActions[] = 'Actions\ForceDeleteAction::make(),';
                $editPageActions[] = 'Actions\RestoreAction::make(),';
            }

            $editPageActions = implode(PHP_EOL, $editPageActions);

            $this->copyStubToApp('ResourceEditPage', $editResourcePagePath, [
                'actions' => $this->indentString($editPageActions, 3),
                'namespace' => "{$resourceNamespace}\\Pages",
                'resource' => $resource,
                'resourceNamespace' => $resourceNamespace,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $editResourcePageClass,
            ]);
        }
        $resourceManagersDir = Str::of($resourceNamespace)->replace('\\', '/')->rtrim('/')->append('/RelationManagers');
        $this->ensureSubdirectoryExists($resourceManagersDir);

        $this->info("Successfully created {$resource}!");

        return static::SUCCESS;
    }

    public function getModuleNamespace()
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }

    private function ensureSubdirectoryExists(string $path): void
    {
        $filesystem = app(Filesystem::class);
        $filesystem->ensureDirectoryExists(
            Str::of($path)->rtrim('/')->toString(),
        );
    }
}
