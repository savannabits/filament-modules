<?php

namespace Coolsam\Modules\Tests\Support;

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
