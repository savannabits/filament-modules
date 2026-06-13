<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Clusters\Cluster;
use Filament\Commands\MakePageCommand;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\Panel;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Support\Facades\FilamentCli;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class ModuleMakeFilamentPageCommand extends MakePageCommand
{
    use GeneratesModularFiles;

    protected $name = 'module:make:filament-page';

    protected $description = 'Create a new Filament page class in the specified module';

    protected string $type = 'Page';

    protected $aliases = [
        'module:filament:page',
        'module:filament:make-page',
    ];

    protected function getRelativeNamespace(): string
    {
        return 'Filament\\Pages';
    }

    /**
     * @throws NoDefaultPanelSetException
     */
    public function handle(): int
    {
        $this->ensureModuleArgument();
        $this->ensurePanel();

        return parent::handle();
    }

    public function ensureModuleArgument(): void
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

    /**
     * @throws NoDefaultPanelSetException
     * @throws \Exception
     */
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
            ])->mapWithKeys(function (Panel | Cluster $panel) {
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

    protected function configureCluster(): void
    {
        if ($this->hasResource) {
            $this->configureClusterFqn(
                initialQuestion: 'Is the resource in a cluster?',
                question: 'Which cluster is the resource in?',
            );
        } else {
            $clusters = FilamentModules::getModuleClusters($this->argument('module'));
            if (empty($clusters)) {
                $this->clusterFqn = null;

                return;
            }
            if (confirm('Would you like to create the page in a cluster?', false)) {
                if (count($clusters) === 1) {
                    $this->clusterFqn = Arr::first($clusters);
                    // Show this to the user and ask to continue
                    confirm("The page will be created in the cluster: {$this->clusterFqn}. Proceed?", true);
                } else {
                    $this->clusterFqn = select(
                        label: 'Please select the cluster to create the page in:',
                        options: $clusters,
                        default: $clusters[0],
                    );
                }
            }
        }

        if (blank($this->clusterFqn)) {
            return;
        }

        $this->configureClusterPagesLocation();
        $this->configureClusterResourcesLocation();
    }

    protected function configurePagesLocation(): void
    {
        if (filled($this->resourceFqn)) {
            return;
        }

        if (filled($this->clusterFqn)) {
            return;
        }

        if (! FilamentModules::getMode()->shouldRegisterPanels()) {
            $this->pagesNamespace = $this->getModule()->appNamespace('Filament\\Pages');
            $this->pagesDirectory = $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR);

            return;
        }

        $panelModules = FilamentModules::getModulePanels($this->argument('module'));
        if (empty($panelModules) || ! collect($panelModules)->contains(fn (
            Panel $panel
        ) => $panel->getId() === $this->panel->getId())) {
            $this->pagesNamespace = $this->getModule()->appNamespace('Filament\\Pages');
            $this->pagesDirectory = $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR);

            return;
        }

        $directories = $this->panel->getPageDirectories();
        $namespaces = $this->panel->getPageNamespaces();

        foreach ($directories as $index => $directory) {
            if (str($directory)->startsWith(base_path('vendor'))) {
                unset($directories[$index]);
                unset($namespaces[$index]);
            }
        }

        if (count($namespaces) < 2) {
            $this->pagesNamespace = (Arr::first($namespaces) ?? $this->getModule()->appNamespace('Filament\\Pages'));
            $this->pagesDirectory = (Arr::first($directories) ?? $this->getModule()->appPath('Filament' . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR));

            return;
        }

        $keyedNamespaces = array_combine(
            $namespaces,
            $namespaces,
        );

        $this->pagesNamespace = search(
            label: 'Which namespace would you like to create this page in?',
            options: function (?string $search) use ($keyedNamespaces): array {
                if (blank($search)) {
                    return $keyedNamespaces;
                }

                $search = str($search)->trim()->replace(['\\', '/'], '');

                return array_filter(
                    $keyedNamespaces,
                    fn (string $namespace): bool => str($namespace)->replace(['\\', '/'], '')->contains(
                        $search,
                        ignoreCase: true
                    )
                );
            },
        );
        $this->pagesDirectory = $directories[array_search($this->pagesNamespace, $namespaces)];
    }

    protected function configureLocation(): void
    {
        $this->fqn = $this->pagesNamespace . '\\' . $this->fqnEnd;

        if ((! $this->hasResource) || ($this->resourcePageType === ResourcePage::class)) {
            $componentLocations = FilamentCli::getComponentLocations();
            if (FilamentModules::getMode()->shouldRegisterPanels()) {
                $modelPanels = FilamentModules::getModulePanels($this->argument('module'));
                $modelPanel = collect($modelPanels)->first(fn (Panel | Cluster $panel) => $panel->getId() === $this->panel->getId());
            } else {
                $modelPanel = null;
            }
            $pageComponent = FilamentModules::getModuleFilamentPageComponentLocation($this->getModule()->getName(), panelId: $modelPanel?->getId(), forCluster: (bool) $this->clusterFqn);
            $componentLocations[$pageComponent['namespace']] = $pageComponent;
            $matchingComponentLocationNamespaces = collect($componentLocations)
                ->keys()
                ->filter(fn (string $namespace): bool => str($this->fqn)->startsWith($namespace));
            // Manually add this module's namespace there
            $v = str($this->fqn)
                ->whenContains(
                    'Filament\\',
                    fn (Stringable $fqn) => $fqn->after('Filament\\')->prepend('Filament\\'),
                    fn (Stringable $fqn) => $fqn->replaceFirst(app()->getNamespace(), ''),
                )
                ->replace('\\', '/')
                ->explode('/')
                ->map(Str::kebab(...))
                ->implode('.');
            [
                $this->view,
                $this->viewPath,
            ] = $this->askForViewLocation(
                view: $v,
                question: 'Where would you like to create the Blade view for the page?',
                defaultNamespace: (count($matchingComponentLocationNamespaces) === 1)
                    ? $componentLocations[Arr::first($matchingComponentLocationNamespaces)]['viewNamespace'] ?? null
                    : null,
            );
        }
    }

    protected function getDefaultStubPath(): string
    {
        // I want to reuse filament's stubs where they are, without copying them to my app
        return base_path('vendor/filament/filament/stubs');
    }
}
