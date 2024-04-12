<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Illuminate\Console\GeneratorCommand;

class ModuleMakeFilamentPluginCommand extends GeneratorCommand
{
    use GeneratesModularFiles;

    protected $name = 'module:make:filament-plugin';

    protected $description = 'Create a new Filament Plugin class in the module';

    protected $type = 'Filament Plugin';

    protected function getRelativeNamespace(): string
    {
        return 'App\\Filament';
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/filament-plugin.stub');
    }

    protected function stubReplacements(): array
    {
        return [
            'moduleStudlyName' => $this->getModule()->getStudlyName(),
        ];
    }
}