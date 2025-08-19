<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Filament\Commands\MakeResourceCommand;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\select;

class ModuleMakeFilamentResourceCommand extends MakeResourceCommand
{
    use GeneratesModularFiles;

    protected $name = 'module:make:filament-resource';

    protected $description = 'Create a new Filament resource class in the specified module';

    protected string $type = 'Resource';

    protected $aliases = [
        'module:filament:resource',
        'module:filament:make-resource',
    ];

    use GeneratesModularFiles;

    protected function getDefaultStubPath(): string
    {
        return base_path('vendor/filament/filament/stubs');
    }

    protected function getRelativeNamespace(): string
    {
        return 'Filament\\Resources';
    }

    public function handle(): int
    {
        $this->ensureModuleArgument();
        $this->ensureModelNamespace();

        return parent::handle();
    }

    public function ensureModuleArgument(): void
    {
        if (! $this->argument('module')) {
            $module = select('Please select the module to create the resource in:', Module::allEnabled());
            if (! $module) {
                $this->error('No module selected. Aborting resource creation.');
                exit(1);
            }
            $this->input->setArgument('module', $module);
        }
    }

    public function ensureModelNamespace(): void
    {
        $modelNamespace = $this->input->getOption('model-namespace');
        if (! $modelNamespace) {
            // try to get from name
            $name = $this->input->getArgument('model');
            if ($name) {
                $modelName = str_replace('Resource', '', class_basename($name));
            } else {
                $modelName = select('Please select the model within this module for the resource:', $this->possibleFqnModels());
            }
            $modelNamespace = $this->rootNamespace() . '\\Models';
            // Ask to select model namespace

            if (! $modelName) {
                $this->error('No model namespace selected. Aborting resource creation.');
                exit(1);
            }

            $modelName = class_basename($modelName);

            $this->input->setOption('model-namespace', $modelNamespace);
            $this->input->setArgument('model', $modelName);

            $this->output->info("Using model namespace: {$modelNamespace}");
            $this->output->info("Using model name: {$modelName}");
        }
    }
}
