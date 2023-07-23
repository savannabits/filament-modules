<?php

namespace Coolsam\Modules\Commands;

use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Concerns\CanValidateInput;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Nwidart\Modules\Traits\ModuleCommandTrait;

class ModuleMakePanelCommand extends Command
{
    use CanManipulateFiles;
    use CanValidateInput;
    use ModuleCommandTrait;

    protected $description = 'Create a new Filament panel in a module';

    protected $signature = 'module:make-filament-panel {id?} {module?} {--F|force}';
    protected ?Module $module = null;
    private $basePath = 'Providers/Filament';

    public function handle(): int
    {
        $id = Str::of($this->argument('id') ?? $this->askRequired('ID (e.g. `app`)', 'id'));

        $class = $id
            ->studly()
            ->append('PanelProvider');
        $module = $this->argument('module');
        if (!$module) {
            try {
                $usedNow = app('modules')->getUsedNow();
            } catch (\Throwable $exception) {
                $usedNow = null;
            }
            $module = Str::of($this->askRequired('Module Name (e.g. `Sales`)', 'module', $usedNow))->toString();
        }
        $this->module = app('modules')->findOrFail($module);

        $path = str($this->module->getExtraPath("{$this->basePath}/$class"))
            ->replace('\\', '/')
            ->append('.php')->toString();

        if (!$this->option('force') && $this->checkForCollision([$path])) {
            return static::INVALID;
        }
        $namespace = Str::of($this->basePath)
            ->replace('/','\\')
            ->prepend('\\')
            ->prepend($this->getModuleNamespace());

        $moduleJson = static::readModuleJson($this->getModuleName());
//        $panelPath = Str::of($this->module->getLowerName());
        $panelPath = $id->prepend("/")->prepend($this->module->getLowerName());

        if (collect($moduleJson['providers'])->filter(
            fn($provider) => Str::of($provider)->contains('PanelProvider') && !Str::of($provider)->contains($class)
        )->count())  {
            $panelPath = $id->prepend("/")->prepend($this->module->getLowerName());
        }

        $this->copyStubToApp('PanelProvider', $path, [
            'namespace'             => $namespace,
            'Module'                => $this->module->getStudlyName(),
            'module_namespace'      => $this->getModuleNamespace(),
            'class'                 => $class,
            'directory'             => $id->studly()->toString(),
            'panel_path'            => $panelPath->toString(),
            'id'                    => $id->prepend("::")->prepend($this->module->getLowerName())->lcfirst(),
        ]);

        $providers = collect($moduleJson['providers']);
        $provider = "$namespace\\$class";
        if (!$providers->contains($provider)) {
            $moduleJson['providers'][] = $provider;
            static::writeToModuleJson($this->getModuleName(),$moduleJson);
        }
        $this->components->info("Successfully created {$class}!");

        return static::SUCCESS;
    }
    public function getModuleNamespace(): string
    {
        return $this->laravel['modules']->config('namespace').'\\'.$this->getModuleName();
    }
    public static function readModuleJson(string $moduleName) {
        return json_decode(file_get_contents(module_path($moduleName,'module.json')),true);
    }
    public static function writeToModuleJson($moduleName, array $data): bool|int
    {
        $jsonString = json_encode($data, JSON_PRETTY_PRINT);
        return file_put_contents(module_path($moduleName,'module.json'),$jsonString);
    }
}
