<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Coolsam\Modules\Facades\FilamentModules;
use Filament\Clusters\Cluster;
use Filament\Commands\MakeResourceCommand;
use Filament\Facades\Filament;
use Filament\Forms\Commands\Concerns\CanGenerateForms;
use Filament\Panel;
use Filament\Support\Commands\Concerns\CanIndentStrings;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Filament\Tables\Commands\Concerns\CanGenerateTables;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModuleMakeFilamentResourceCommand extends MakeResourceCommand
{
    use GeneratesModularFiles;
    protected function getDefaultStubPath(): string
    {
        return base_path('vendor/filament/filament/stubs');
    }

    protected function getRelativeNamespace(): string
    {
        return 'Filament';
    }
}
