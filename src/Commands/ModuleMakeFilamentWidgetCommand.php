<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Panel;
use Filament\Support\Facades\FilamentCli;
use Filament\Widgets\Commands\MakeWidgetCommand;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class ModuleMakeFilamentWidgetCommand extends MakeWidgetCommand
{
    use GeneratesModularFiles;

    protected $name = 'module:make:filament-widget';

    protected $description = 'Create a new Filament Widget class in the module';

    protected $aliases = [
        'module:filament:widget',
        'module:filament:make-widget',
    ];

    public function handle(): int
    {
        $this->ensureModule();
        $this->ensurePanel();

        return parent::handle();
    }

    protected function getRelativeNamespace(): string
    {
        return 'Filament\\Widgets';
    }

    public function ensureModule()
    {
        if (! $this->argument('module')) {
            $module = select('Please select the module to create the page in:', Module::allEnabled());
            if (! $module) {
                $this->error('No module selected. Aborting page creation.');
                exit(1);
            }
            $this->input->setArgument('module', $module);
        }
    }

    public function ensurePanel(): void
    {
        if (! $this->option('panel')) {
            $defaultPanel = filament()->getDefaultPanel();

            if (! FilamentModules::getMode()->shouldRegisterPanels()) {
                $this->input->setOption('panel', $defaultPanel->getId());

                return;
            }
            $panels = FilamentModules::getModulePanels($this->argument('module'));
            if (empty($panels)) {
                $this->input->setOption('panel', $defaultPanel->getId());

                return;
            }
            $options = collect([
                $defaultPanel,
                ...$panels,
            ])->mapWithKeys(function (Panel $panel) {
                return [$panel->getId() => $panel->getId()];
            })->toArray();

            $selectedPanel = select(
                'Please select the panel to create the page in:',
                $options,
                default: $defaultPanel->getId(),
            );

            if (! $selectedPanel) {
                $this->error('No panel selected. Aborting page creation.');
                exit(1);
            }

            $this->input->setOption('panel', $selectedPanel);
        }
    }

    protected function configureWidgetsLocation(): void
    {
        if (filled($this->resourceFqn)) {
            return;
        }

        if (! $this->panel) {
            [
                $this->widgetsNamespace,
                $this->widgetsDirectory,
            ] = $this->askForLivewireComponentLocation(
                question: 'Where would you like to create the widget?',
            );

            return;
        }

        // If this is a module panel, then set the widget directory and namespace to the module's Filament Widgets directory
        $modulePanels = FilamentModules::getModulePanels($this->getModule());
        if (in_array($this->panel, $modulePanels, true)) {
            $directories = $this->panel->getWidgetDirectories();
            $namespaces = $this->panel->getWidgetNamespaces();
        } else {
            // Default to the module's filament widgets directory
            $directories = [
                $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Widgets'),
            ];
            $namespaces = [
                $this->getModule()->appNamespace('Filament\\Widgets'),
            ];
        }

        foreach ($directories as $index => $directory) {
            if (str($directory)->startsWith(base_path('vendor'))) {
                unset($directories[$index]);
                unset($namespaces[$index]);
            }
        }

        if (count($namespaces) < 2) {
            $this->widgetsNamespace = (Arr::first($namespaces) ?? $this->getModule()->appNamespace('Filament\\Widgets'));
            $this->widgetsDirectory = (Arr::first($directories) ?? $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Widgets'));

            return;
        }

        $keyedNamespaces = array_combine(
            $namespaces,
            $namespaces,
        );

        $this->widgetsNamespace = search(
            label: 'Which namespace would you like to create this widget in?',
            options: function (?string $search) use ($keyedNamespaces): array {
                if (blank($search)) {
                    return $keyedNamespaces;
                }

                $search = str($search)->trim()->replace(['\\', '/'], '');

                return array_filter($keyedNamespaces, fn (string $namespace): bool => str($namespace)->replace(['\\', '/'], '')->contains($search, ignoreCase: true));
            },
        );
        $this->widgetsDirectory = $directories[array_search($this->widgetsNamespace, $namespaces)];
    }

    protected function configureLocation(): void
    {
        $this->fqn = $this->widgetsNamespace . '\\' . $this->fqnEnd;

        if ($this->type === Widget::class) {
            $componentLocations = FilamentCli::getLivewireComponentLocations();

            $matchingComponentLocationNamespaces = collect($componentLocations)
                ->keys()
                ->filter(fn (string $namespace): bool => str($this->fqn)->startsWith($namespace));

            [
                $this->view,
                $this->viewPath,
            ] = $this->askForViewLocation(
                view: str($this->fqn)
                    ->whenContains(
                        'Filament\\',
                        fn (Stringable $fqn) => $fqn->after('Filament\\')->prepend('Filament\\'),
                        fn (Stringable $fqn) => $fqn
                            ->afterLast('\\Livewire\\')
                            ->prepend('Livewire\\'),
                    )
                    ->replace('\\', '/')
                    ->explode('/')
                    ->map(Str::kebab(...))
                    ->implode('.'),
                question: 'Where would you like to create the Blade view for the widget?',
                defaultNamespace: (count($matchingComponentLocationNamespaces) === 1)
                    ? $componentLocations[Arr::first($matchingComponentLocationNamespaces)]['viewNamespace'] ?? null
                    : null,
            );
        }
    }

    protected function askForLivewireComponentLocation(string $question = 'Where would you like to create the Livewire component?'): array
    {
        $locations = FilamentCli::getLivewireComponentLocations();

        if (blank($locations)) {
            return [
                $this->getModule()->appNamespace('Livewire'),
                $this->getModule()->appPath('Livewire'),
                '',
            ];
        }

        $options = [
            null => $this->getModule()->appNamespace('Livewire'),
            ...array_combine(
                array_keys($locations),
                array_keys($locations),
            ),
        ];

        $namespace = select(
            label: $question,
            options: $options,
        );

        if (blank($namespace)) {
            return [
                $this->getModule()->appNamespace('Livewire'),
                $this->getModule()->appPath('Livewire'),
                '',
            ];
        }

        return [
            $namespace,
            $locations[$namespace]['path'],
            $locations[$namespace]['viewNamespace'] ?? null,
        ];
    }
}
