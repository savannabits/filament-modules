<?php

namespace Coolsam\Modules;

use Coolsam\Modules\Enums\ConfigMode;
use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Traits\Macroable;
use Nwidart\Modules\Facades\Module;
use Symfony\Component\Process\Process;

class Modules
{
    use Macroable;

    public function getModule(string $name): \Nwidart\Modules\Module
    {
        return Module::findOrFail($name);
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
        $pattern = $panelPath . DIRECTORY_SEPARATOR . '*PanelProvider.php';
        $panelPaths = glob($pattern);
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
        $pattern = $clusterPath . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*Cluster.php';
        $clusterPaths = glob($pattern);

        return collect($clusterPaths)->map(function ($path) {
            // Convert the path to a namespace
            return $this->convertPathToNamespace($path);
        })->all();
    }

    public function convertPathToNamespace(string $fullPath): string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $fullPath);
        $appFolder = trim(config('modules.paths.app_folder', 'app'), '/\\');
        $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim(config('modules.paths.modules', base_path('Modules')), '/\\'));
        $appSegment = $appFolder . DIRECTORY_SEPARATOR;

        $relative = str($normalizedPath)->afterLast($base)->ltrim(DIRECTORY_SEPARATOR);

        if (str($relative)->startsWith($appSegment)) {
            $relative = str($relative)->after($appSegment);
        } else {
            $relative = str($relative)->replace(DIRECTORY_SEPARATOR . $appSegment, DIRECTORY_SEPARATOR);
        }

        return str($relative)
            ->prepend(DIRECTORY_SEPARATOR)
            ->prepend(config('modules.namespace', 'Modules'))
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->replace('\\\\', '\\')
            ->rtrim('.php')
            ->explode(DIRECTORY_SEPARATOR)
            ->map(fn ($piece) => str($piece)->studly()->toString())
            ->implode('\\');
    }

    public function findModuleNameForPath(string $path): ?string
    {
        $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $modulesPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, config('modules.paths.modules', base_path('Modules')));

        $directory = is_file($normalizedPath) ? dirname($normalizedPath) : $normalizedPath;

        while (str($directory)->startsWith($modulesPath) && $directory !== $modulesPath) {
            $moduleJsonPath = $directory . DIRECTORY_SEPARATOR . 'module.json';

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

        $content = file_get_contents($providerPath);

        if ($content === false || ! preg_match('/^namespace\s+([^;]+);/m', $content, $matches)) {
            return null;
        }

        return trim($matches[1]) . '\\' . basename($providerPath, '.php');
    }

    public function resolveProviderClass(string $providerPath): string
    {
        return $this->resolveClassFromProviderFile($providerPath) ?? $this->convertPathToNamespace($providerPath);
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
