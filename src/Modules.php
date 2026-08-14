<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Enums\ConfigMode;
use Coolsam\Modules\Support\NamespaceResolver;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Traits\Macroable;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as NwidartModule;
use Symfony\Component\Process\Process;

class Modules
{
    use Macroable;

    protected ?NamespaceResolver $namespaceResolver = null;

    public function getModule(string $name): NwidartModule
    {
        return Module::findOrFail($name);
    }

    /**
     * Get the NamespaceResolver instance (lazy loading).
     */
    public function getNamespaceResolver(): NamespaceResolver
    {
        if (! $this->namespaceResolver instanceof NamespaceResolver) {
            $this->namespaceResolver = app()->make(NamespaceResolver::class);
        }

        return $this->namespaceResolver;
    }

    /**
     * The base namespace of a module, optionally suffixed with a relative one.
     *
     * Reads the namespace the module actually declares instead of assuming
     * `config('modules.namespace')` applies to every module.
     */
    public function getModuleNamespace(NwidartModule | string $module, string $relativeNamespace = ''): string
    {
        $module = is_string($module) ? $this->getModule($module) : $module;

        return $this->getNamespaceResolver()->moduleNamespace($module, $relativeNamespace);
    }

    /**
     * Resolve the class a path maps to, or null when it cannot be determined.
     */
    public function resolveClass(string $path): ?string
    {
        return $this->getNamespaceResolver()->resolveClass($path);
    }

    /**
     * @return Panel[]
     */
    public function getModulePanels(string $moduleName): array
    {
        $module = $this->getModule($moduleName);

        // Scan the Providers/Filament directory of the panel for providers
        $panelPath = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'Filament');
        $module = Module::find($moduleName);
        if (! $module || ! is_dir($panelPath)) {
            return [];
        }
        $panelPaths = $this->globFiles($panelPath, '*PanelProvider.php');
        $panels_ids = collect($panelPaths)->map(function ($path) use ($moduleName) {
            // Convert the path to a namespace
            $namespace = $this->convertPathToNamespace($path);
            // Get the panel ID from the class name
            $id = str($namespace)->afterLast('\\')->before('PanelProvider')->kebab()->lower();

            return str($id)->prepend('-')->prepend($this->getModule($moduleName)->getKebabName());
        });

        return collect(filament()->getPanels())->filter(function ($panel) use ($panels_ids) {
            return $panels_ids->contains($panel->getId());
        })->values()->all();
    }

    public function getModuleClusters(string $moduleName)
    {
        $module = $this->getModule($moduleName);

        // Scan the Clusters directory of the module for clusters
        $clusterPath = $module->appPath('Filament' . DIRECTORY_SEPARATOR . 'Clusters');
        if (! is_dir($clusterPath)) {
            return [];
        }
        $clusterPaths = $this->globFiles($clusterPath, '*', '*Cluster.php');

        return collect($clusterPaths)->map(function ($path) {
            // Convert the path to a namespace
            return $this->convertPathToNamespace($path);
        })->filter()->values()->all();
    }

    /**
     * Convert a path to the class it maps to.
     *
     * Delegates to the NamespaceResolver so that modules living outside
     * `config('modules.namespace')` resolve to the namespace they declare.
     */
    public function convertPathToNamespace(string $fullPath): string
    {
        return $this->resolveClass($fullPath) ?? '';
    }

    public function findModuleNameForPath(string $path): ?string
    {
        $resolver = $this->getNamespaceResolver();
        $modulesPath = $resolver->normalizePath((string) config('modules.paths.modules', base_path('Modules')));
        $normalizedPath = $resolver->normalizePath($path);

        $directory = is_file($path) ? dirname($normalizedPath) : $normalizedPath;

        while ($modulesPath !== '' && $directory !== $modulesPath && str_starts_with(
            DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($directory) : $directory,
            DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($modulesPath) : $modulesPath,
        )) {
            $moduleJsonPath = $directory . '/module.json';

            if (is_file($moduleJsonPath)) {
                $moduleJson = json_decode((string) file_get_contents($moduleJsonPath), true);

                return is_array($moduleJson) ? ($moduleJson['name'] ?? basename($directory)) : basename($directory);
            }

            $parentDirectory = dirname($directory);

            if ($parentDirectory === $directory) {
                break;
            }

            $directory = $parentDirectory;
        }

        return null;
    }

    public function resolveClassFromProviderFile(string $providerPath): ?string
    {
        if (! is_file($providerPath)) {
            return null;
        }

        return $this->resolveClass($providerPath);
    }

    public function resolveProviderClass(string $providerPath): string
    {
        return $this->resolveClassFromProviderFile($providerPath) ?? $this->convertPathToNamespace($providerPath);
    }

    /**
     * Build a glob pattern from path segments, tolerating the mixed separators
     * and trailing slashes that `modules.paths.*` config values may carry.
     */
    public function globPattern(string ...$segments): string
    {
        $normalized = [];

        foreach (array_values($segments) as $index => $segment) {
            $segment = str_replace('\\', '/', $segment);
            // Keep a leading slash on the first segment: it may be an absolute path.
            $segment = $index === 0 ? rtrim($segment, '/') : trim($segment, '/');

            if ($segment !== '') {
                $normalized[] = $segment;
            }
        }

        return implode('/', $normalized);
    }

    /**
     * @return array<int, string>
     */
    public function globFiles(string ...$segments): array
    {
        return array_filter((array) glob($this->globPattern(...$segments)));
    }

    public function execCommand(string $command, ?Command $artisan = null): void
    {
        $process = Process::fromShellCommandline($command);
        $process->start();
        foreach ($process as $type => $data) {
            if (! $artisan) {
                echo $data;
            } else {
                $artisan->info(trim($data));
            }
        }
    }

    public function packagePath(string $path = ''): string
    {
        // return the base path of this package
        return dirname(__DIR__) . ($path ? DIRECTORY_SEPARATOR . trim($path, DIRECTORY_SEPARATOR) : '');
    }

    public function getMode(): ?ConfigMode
    {
        return ConfigMode::tryFrom(config('filament-modules.mode', ConfigMode::BOTH->value));
    }

    public function getModuleFilamentPageComponentLocation(string $moduleName, ?string $panelId = null, bool $forCluster = false): array
    {
        $module = $this->getModule($moduleName);
        $viewPath = $module->getExtraPath('resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'filament' . DIRECTORY_SEPARATOR . 'pages');
        $componentNamespace = $module->appNamespace('Filament\\Pages');
        if ($panelId) {
            $panelDir = str($panelId)->studly()->toString();
            $viewPath = $module->getExtraPath('resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'filament' . DIRECTORY_SEPARATOR . str($panelDir)->kebab()->toString());
            $componentNamespace = $module->appNamespace('Filament\\' . $panelDir);
        } elseif ($forCluster) {
            $viewPath = $module->getExtraPath('resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'filament' . DIRECTORY_SEPARATOR . 'clusters');
            $componentNamespace = $module->appNamespace('Filament\\Clusters');
        }
        // Create if it doesn't exist
        if (! is_dir($viewPath)) {
            mkdir($viewPath, 0755, true);
        }
        $viewNamespace = $module->getLowerName();

        return [
            'namespace' => $componentNamespace,
            'path' => $viewPath,
            'viewNamespace' => $viewNamespace,
        ];
    }
}
