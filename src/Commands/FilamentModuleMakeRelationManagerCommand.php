<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Commands\MakeRelationManagerCommand;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class FilamentModuleMakeRelationManagerCommand extends MakeRelationManagerCommand
{
    use ModuleCommandTrait;

    protected $description = 'Creates a Filament relation manager class for a resource.';

    protected $signature = 'module:make-filament-relation-manager {module?} {resource?} {relationship?} {recordTitleAttribute?} {--attach} {--associate} {--soft-deletes} {--view} {--F|force}';

    protected ?Module $module;

    public function handle(): int
    {
        $module = (string) Str::of($this->argument('module') ?? $this->askRequired('Module Name (e.g. `sales`)', 'module'));
        $this->module = app('modules')->findOrFail($this->getModuleName());

        $resourcePath = module_path($module, 'Filament/Resources/');
        $resourceNamespace = $this->getModuleNamespace().'\\Filament\\Resources';

        $resource = (string) Str::of($this->argument('resource') ?? $this->askRequired('Resource (e.g. `DepartmentResource`)', 'resource'))
            ->studly()
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');

        if (! Str::of($resource)->endsWith('Resource')) {
            $resource .= 'Resource';
        }

        $relationship = (string) Str::of($this->argument('relationship') ?? $this->askRequired('Relationship (e.g. `members`)', 'relationship'))
            ->trim(' ');
        $managerClass = (string) Str::of($relationship)
            ->studly()
            ->append('RelationManager');

        $recordTitleAttribute = (string) Str::of($this->argument('recordTitleAttribute') ?? $this->askRequired('Title attribute (e.g. `name`)', 'title attribute'))
            ->trim(' ');

        $path = (string) Str::of($managerClass)
            ->prepend("{$resourcePath}/{$resource}/RelationManagers/")
            ->replace('\\', '/')
            ->append('.php');

        if (! $this->option('force') && $this->checkForCollision([
            $path,
        ])) {
            return static::INVALID;
        }

        $tableHeaderActions = [];

        $tableHeaderActions[] = 'Tables\Actions\CreateAction::make(),';

        if ($this->option('associate')) {
            $tableHeaderActions[] = 'Tables\Actions\AssociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableHeaderActions[] = 'Tables\Actions\AttachAction::make(),';
        }

        $tableHeaderActions = implode(PHP_EOL, $tableHeaderActions);

        $tableActions = [];

        if ($this->option('view')) {
            $tableActions[] = 'Tables\Actions\ViewAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\EditAction::make(),';

        if ($this->option('associate')) {
            $tableActions[] = 'Tables\Actions\DissociateAction::make(),';
        }

        if ($this->option('attach')) {
            $tableActions[] = 'Tables\Actions\DetachAction::make(),';
        }

        $tableActions[] = 'Tables\Actions\DeleteAction::make(),';

        if ($this->option('soft-deletes')) {
            $tableActions[] = 'Tables\Actions\ForceDeleteAction::make(),';
            $tableActions[] = 'Tables\Actions\RestoreAction::make(),';
        }

        $tableActions = implode(PHP_EOL, $tableActions);

        $tableBulkActions = [];

        if ($this->option('associate')) {
            $tableBulkActions[] = 'Tables\Actions\DissociateBulkAction::make(),';
        }

        if ($this->option('attach')) {
            $tableBulkActions[] = 'Tables\Actions\DetachBulkAction::make(),';
        }

        $tableBulkActions[] = 'Tables\Actions\DeleteBulkAction::make(),';

        $eloquentQuery = '';

        if ($this->option('soft-deletes')) {
            $tableBulkActions[] = 'Tables\Actions\RestoreBulkAction::make(),';
            $tableBulkActions[] = 'Tables\Actions\ForceDeleteBulkAction::make(),';

            $eloquentQuery .= PHP_EOL.PHP_EOL.'protected function getTableQuery(): Builder';
            $eloquentQuery .= PHP_EOL.'{';
            $eloquentQuery .= PHP_EOL.'    return parent::getTableQuery()';
            $eloquentQuery .= PHP_EOL.'        ->withoutGlobalScopes([';
            $eloquentQuery .= PHP_EOL.'            SoftDeletingScope::class,';
            $eloquentQuery .= PHP_EOL.'        ]);';
            $eloquentQuery .= PHP_EOL.'}';
        }

        $tableBulkActions = implode(PHP_EOL, $tableBulkActions);

        $this->copyStubToApp('RelationManager', $path, [
            'eloquentQuery' => $this->indentString($eloquentQuery, 1),
            'namespace' => "{$resourceNamespace}\\{$resource}\\RelationManagers",
            'managerClass' => $managerClass,
            'recordTitleAttribute' => $recordTitleAttribute,
            'relationship' => $relationship,
            'tableActions' => $this->indentString($tableActions, 4),
            'tableBulkActions' => $this->indentString($tableBulkActions, 4),
            'tableFilters' => $this->indentString(
                $this->option('soft-deletes') ? 'Tables\Filters\TrashedFilter::make()' : '//',
                4,
            ),
            'tableHeaderActions' => $this->indentString($tableHeaderActions, 4),
        ]);

        $this->info("Successfully created {$managerClass}!");

        $this->info("Make sure to register the relation in `{$resource}::getRelations()`.");

        return static::SUCCESS;
    }

    public function getModuleNamespace()
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }
}
