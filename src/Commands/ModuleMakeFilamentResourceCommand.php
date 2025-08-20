<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Commands\MakeResourceCommand;
use Illuminate\Support\Arr;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\search;
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
        $this->ensurePanel();

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

    public function ensurePanel()
    {
        $defaultPanel = filament()->getDefaultPanel();
        if (! FilamentModules::getMode()->shouldRegisterPanels()) {
            $this->panel = $defaultPanel;
        } else {
            $modulePanels = FilamentModules::getModulePanels($this->getModule());
            $options = [
                $defaultPanel->getId(),
                ...collect($modulePanels)->map(fn ($panel) => $panel->getId())->values()->all(),
            ];
            $panelId = select(
                label: 'Please select the Filament panel to create the resource in:',
                options: $options,
                default: $defaultPanel->getId(),
            );
            $this->input->setOption('panel', $panelId);
            $this->panel = filament()->getPanel($panelId, isStrict: false);
            if (! $this->panel) {
                $this->error("Panel [{$panelId}] not found. Aborting resource creation.");
                exit(1);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function getResourcesLocation(string $question): array
    {
        $modulePanels = FilamentModules::getModulePanels($this->getModule());
        $mode = ConfigMode::tryFrom(config('filament-modules.mode', ConfigMode::BOTH->value));
        if ($mode->shouldRegisterPanels() && in_array($this->panel->getId(), collect($modulePanels)->map(fn ($panel) => $panel->getId())->all())) {
            $directories = $this->panel->getResourceDirectories();
            $namespaces = $this->panel->getResourceNamespaces();
        } else {
            // Default to the module's filament resources directory
            $directories = [
                $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Resources'),
            ];
            $namespaces = [
                $this->getModule()->appNamespace('Filament\\Resources'),
            ];
        }

        foreach ($directories as $index => $directory) {
            if (str($directory)->startsWith(base_path('vendor'))) {
                unset($directories[$index]);
                unset($namespaces[$index]);
            }
        }

        if (count($namespaces) < 2) {
            return [
                (Arr::first($namespaces) ?? $this->getModule()->appNamespace('Filament\\Resources')),
                (Arr::first($directories) ?? $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Resources')),
            ];
        }

        if ($this->option('resource-namespace')) {
            return [
                (string) $this->option('resource-namespace'),
                $directories[array_search($this->option('resource-namespace'), $namespaces)],
            ];
        }

        $keyedNamespaces = array_combine(
            $namespaces,
            $namespaces,
        );

        return [
            $namespace = search(
                label: $question,
                options: function (?string $search) use ($keyedNamespaces): array {
                    if (blank($search)) {
                        return $keyedNamespaces;
                    }

                    $search = str($search)->trim()->replace(['\\', '/'], '');

                    return array_filter($keyedNamespaces, fn (string $namespace): bool => str($namespace)->replace(['\\', '/'], '')->contains($search, ignoreCase: true));
                },
            ),
            $directories[array_search($namespace, $namespaces)],
        ];
    }
}
