<?php

namespace Coolsam\Modules\Tests\Support;

use Filament\Panel;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\FileRepository;
use Nwidart\Modules\Laravel\Module as LaravelModule;

trait CreatesTestModules
{
    protected function workbenchPath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2) . '/vendor/orchestra/testbench-core/laravel';

        return $path === '' ? $base : $base . '/' . ltrim($path, '/');
    }

    protected function modulesPath(): string
    {
        return $this->workbenchPath('Modules');
    }

    protected function resetModulesDirectory(): void
    {
        $modulesPath = $this->modulesPath();

        if (is_dir($modulesPath)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($modulesPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        } else {
            mkdir($modulesPath, 0755, true);
        }

        $statusesFile = $this->workbenchPath('modules_statuses.json');

        if (file_exists($statusesFile)) {
            unlink($statusesFile);
        }

        $this->clearModuleRepositoryCache();
    }

    protected function createTestModule(string $name = 'Blog', bool $enabled = true): LaravelModule
    {
        $modulePath = $this->modulesPath() . DIRECTORY_SEPARATOR . $name;

        if (! is_dir($modulePath)) {
            mkdir($modulePath . DIRECTORY_SEPARATOR . 'app', 0755, true);
            file_put_contents($modulePath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
                'name' => $name,
                'alias' => strtolower($name),
                'description' => '',
                'keywords' => [],
                'priority' => 0,
                'providers' => [],
                'files' => [],
            ], JSON_THROW_ON_ERROR));
        }

        $this->clearModuleRepositoryCache();

        $module = Module::findOrFail($name);

        $enabled ? $module->enable() : $module->disable();

        return $module;
    }

    protected function createModuleModel(string $moduleName = 'Blog', string $modelName = 'Post'): void
    {
        $module = $this->createTestModule($moduleName);
        $modelsPath = $module->appPath('Models');

        if (! is_dir($modelsPath)) {
            mkdir($modelsPath, 0755, true);
        }

        file_put_contents($modelsPath . DIRECTORY_SEPARATOR . $modelName . '.php', <<<'PHP'
<?php

namespace Modules\Blog\Models;

class Post
{
}
PHP);
    }

    protected function createModuleCluster(string $moduleName = 'Blog', string $clusterName = 'Settings'): LaravelModule
    {
        $module = $this->createTestModule($moduleName);
        $clusterDir = $module->appPath('Filament' . DIRECTORY_SEPARATOR . 'Clusters' . DIRECTORY_SEPARATOR . $clusterName);

        if (! is_dir($clusterDir)) {
            mkdir($clusterDir, 0755, true);
        }

        file_put_contents($clusterDir . DIRECTORY_SEPARATOR . $clusterName . 'Cluster.php', <<<PHP
<?php

namespace Modules\\{$moduleName}\\Filament\\Clusters\\{$clusterName};

class {$clusterName}Cluster
{
}
PHP);

        return $module;
    }

    protected function createModuleFilamentPluginFile(string $moduleName = 'Blog', string $pluginName = 'BlogAccessPlugin.php'): LaravelModule
    {
        $module = $this->createTestModule($moduleName);
        $pluginDir = $module->appPath('Filament');

        if (! is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }

        file_put_contents($pluginDir . DIRECTORY_SEPARATOR . $pluginName, <<<'PHP'
<?php

namespace Modules\Blog\Filament;

class BlogAccessPlugin
{
}
PHP);

        return $module;
    }

    protected function createModulePanelProvider(
        string $moduleName = 'Blog',
        string $providerClass = 'BlogAdminPanelProvider',
        ?string $namespace = null,
    ): LaravelModule {
        $module = $this->createTestModule($moduleName);
        $providerDir = $module->appPath('Providers' . DIRECTORY_SEPARATOR . 'Filament');

        if (! is_dir($providerDir)) {
            mkdir($providerDir, 0755, true);
        }

        $namespace ??= "Modules\\{$moduleName}\\Providers\\Filament";
        $panelId = $module->getLowerName() . '-admin';
        $panelPath = $module->getLowerName() . '/admin';

        file_put_contents($providerDir . DIRECTORY_SEPARATOR . $providerClass . '.php', <<<PHP
<?php

namespace {$namespace};

use Filament\Panel;
use Filament\PanelProvider;

class {$providerClass} extends PanelProvider
{
    public function panel(Panel \$panel): Panel
    {
        return \$panel
            ->id('{$panelId}')
            ->path('{$panelPath}');
    }
}
PHP);

        $providerPath = $providerDir . DIRECTORY_SEPARATOR . $providerClass . '.php';

        if (! class_exists("{$namespace}\\{$providerClass}", false)) {
            require_once $providerPath;
        }

        return $module;
    }

    protected function registerTestPanel(Panel $panel): Panel
    {
        filament()->registerPanel($panel);

        return $panel;
    }

    protected function clearModuleRepositoryCache(): void
    {
        $reflection = new \ReflectionClass(FileRepository::class);

        if ($reflection->hasProperty('modules')) {
            $property = $reflection->getProperty('modules');
            $property->setAccessible(true);
            $property->setValue(null, []);
        }
    }
}
