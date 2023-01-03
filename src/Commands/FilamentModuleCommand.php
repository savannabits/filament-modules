<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Concerns\CanValidateInput;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Nwidart\Modules\Exceptions\ModuleNotFoundException;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class FilamentModuleCommand extends Command
{
    use CanManipulateFiles;
    use CanValidateInput;
    use ModuleCommandTrait;

    protected $description = 'Create a Filament context in a module';

    protected $name = 'module:make-filament-context';

    protected ?Module $module;

    public function handle(): int
    {
        /*$moduleName = Str::of($this->getContextInput())
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\') ?? 'filament';*/
        try {
            $this->module = app('modules')->findOrFail($this->getModuleName());
        } catch (\Throwable $exception) {
            \Log::info($exception->getMessage()." Creating It ...");
            Artisan::call('module:make',['name' => [$this->argument('module')]]);
            $this->module = app('modules')->findOrFail($this->getModuleName());
        } finally {
        }

        $context = Str::of($this->argument('context') ?? 'Filament')->studly();
        $this->copyStubs($context);

        $this->createDirectories($context);

        $this->info("Successfully created {$context} context!");

        if ($this->option('guard')) {
            Artisan::call(
                'make:filament-guard',
                $this->option('force') ?  [
                    'name' => $context, '--force'
                ] : ['name' => $context]
            );
        }

        return static::SUCCESS;
    }

    protected function getContextInput(): string
    {
        return $this->validateInput(
            fn () => $this->argument('module') ?? $this->askRequired('Module Name (e.g. `sales`)', 'module'),
            'module',
            ['required']
        );
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments(): array
    {
        return [
            ['context', InputArgument::REQUIRED, 'The context Name e.g Filament.'],
            ['module', InputArgument::OPTIONAL, 'The name of module will be used.'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['guard', 'G', InputOption::VALUE_NONE, 'Generate a guard middleware.', null],
            ['force', 'F', InputOption::VALUE_NONE, 'Force overwrite of existing files.', null],
        ];
    }

    public function getModuleNamespace() {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }

    protected function copyStubs($context)
    {
        $serviceProviderClass = $context->afterLast('\\')->append('ServiceProvider');

        $contextName = $context->afterLast('\\')->kebab();

        $serviceProviderPath = $serviceProviderClass
            ->prepend('/')
            ->prepend(module_path($this->getModuleName(),'Providers'))
            ->append('.php');

        $configPath = $contextName
            ->prepend($this->module->getLowerName().'-')
            ->prepend('/')
            ->prepend(module_path($this->getModuleName(),'Config'))
            ->append('.php');
        $moduleNs = Str::of($this->getModuleNamespace());

        $contextNamespace = $context
            ->replace('\\', '\\\\')
            ->prepend('\\\\')
            ->prepend($moduleNs->replace('\\','\\\\')->toString());

        if (!$this->option('force') && $this->checkForCollision([
            $serviceProviderPath,
        ])) {
            return static::INVALID;
        }

        if (!$this->option('force') && $this->checkForCollision([
            $configPath,
        ])) {
            return static::INVALID;
        }

        $this->copyStubToApp('ContextServiceProvider', $serviceProviderPath, [
            'class' => (string) $serviceProviderClass,
            'name' => (string) $contextName,
            'Module' => $this->module->getStudlyName(),
            'module' => $this->module->getLowerName(),
            'module_' => $this->module->getSnakeName(),
            'module-' => Str::slug($this->module->getName()),
            'namespace' => $providerNs = $moduleNs->append('\\Providers')->replace('\\\\', '\\'),
        ]);
        // Install the service provider
        $this->installServiceProvider($providerNs->append('\\')->append($serviceProviderClass->toString())->append('::class'));

        // MIDDLEWARE

        $middlewareClass = $context->afterLast('\\')->append('Middleware');

        $middlewarePath = $middlewareClass
            ->prepend(module_path($this->getModuleName(),'Http/Middleware/'))
            ->append('.php');

        if (!$this->option('force') && $this->checkForCollision([$middlewarePath])) {
            return static::INVALID;
        }

        $middlewareNs = $moduleNs->append('\\Http\\\Middleware');
        $this->copyStubToApp('ContextMiddleware', $middlewarePath, [
            'class' => (string) $middlewareClass,
            'context' => (string) $contextName,
            'module' => $this->module->getStudlyName(),
            'namespace' => $middlewareNs->replace('\\\\', '\\')->toString(),
        ]);

        // LOGIN

        $loginClass = $context->afterLast('\\')->append('Login');

        $loginPath = $loginClass
            ->prepend(module_path($this->getModuleName(),'Http/Livewire/Auth/'))
            ->append('.php');

        if (!$this->option('force') && $this->checkForCollision([$loginPath])) {
            return static::INVALID;
        }

        $loginNs = $moduleNs->append('\\Http\\Livewire\\Auth');
        $this->copyStubToApp('ContextLogin', $loginPath, [
            'class' => (string) $loginClass,
            'context' => (string) $contextName,
            'module' => $this->module->getStudlyName(),
            'namespace' => $loginNs->replace('\\\\', '\\')->toString(),
        ]);

        // CONFIG
        $this->copyStubToApp('config', $configPath, [
            'namespace' => (string) $contextNamespace,
            'moduleNamespace' => $moduleNs->replace('\\\\','\\')->toString(),
            'path' => (string) $context->replace('\\', '/'),
            'loginClass' => $loginNs->append('\\')->append($loginClass)->append('::class')->replace('\\\\', '\\')->toString(),
            'authMiddleware' => $middlewareNs->append('\\')->append($middlewareClass)->append('::class')->replace('\\\\', '\\')->toString(),
            'module' => $this->module->getStudlyName(),
        ]);
    }

    protected function createDirectories($context)
    {
        $directoryPath = module_path($this->getModuleName(), (string) $context->replace('\\', '/'));

        app(Filesystem::class)->makeDirectory($directoryPath, force: $this->option('force'));
        app(Filesystem::class)->makeDirectory($directoryPath . '/Pages', force: $this->option('force'));
        app(Filesystem::class)->makeDirectory($directoryPath . '/Resources', force: $this->option('force'));
        app(Filesystem::class)->makeDirectory($directoryPath . '/Widgets', force: $this->option('force'));
    }

    protected function copyStubToApp(string $stub, string $targetPath, array $replacements = []): void
    {
        $filesystem = app(Filesystem::class);

        if (!$this->fileExists($stubPath = base_path("stubs/filament/{$stub}.stub"))) {
            $stubPath = __DIR__ . "/../../stubs/{$stub}.stub";
        }

        $stub = Str::of($filesystem->get($stubPath));

        foreach ($replacements as $key => $replacement) {
            $stub = $stub->replace("{{ {$key} }}", $replacement);
        }

        $stub = (string) $stub;

        $this->writeFile($targetPath, $stub);
    }

    /**
     * Install the service provider in the application configuration file.
     *
     * @param string $after
     * @param string $providerClass | Fully namespaced service class
     * @return void
     */
    protected function installServiceProvider(string $providerClass, string $after='RouteServiceProvider'): void
    {
        if (! Str::contains($appConfig = file_get_contents(config_path('app.php')), $providerClass)) {
            file_put_contents(config_path('app.php'), str_replace(
                'App\\Providers\\'.$after.'::class,',
                'App\\Providers\\'.$after.'::class,'.PHP_EOL."        $providerClass,",
                $appConfig
            ));
        }
    }

    /**
     * Install the middleware to a group in the application Http Kernel.
     *
     * @param  string  $after
     * @param  string  $name
     * @param  string  $group
     * @return void
     */
    protected function installMiddlewareAfter($after, $name, $group = 'web')
    {
        $httpKernel = file_get_contents(app_path('Http/Kernel.php'));

        $middlewareGroups = Str::before(Str::after($httpKernel, '$middlewareGroups = ['), '];');
        $middlewareGroup = Str::before(Str::after($middlewareGroups, "'$group' => ["), '],');

        if (! Str::contains($middlewareGroup, $name)) {
            $modifiedMiddlewareGroup = str_replace(
                $after.',',
                $after.','.PHP_EOL.'            '.$name.',',
                $middlewareGroup,
            );

            file_put_contents(app_path('Http/Kernel.php'), str_replace(
                $middlewareGroups,
                str_replace($middlewareGroup, $modifiedMiddlewareGroup, $middlewareGroups),
                $httpKernel
            ));
        }
    }
}
