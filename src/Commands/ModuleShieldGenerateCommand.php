<?php

namespace Savannabits\FilamentModules\Commands;

use Filament\Facades\Filament;
use Filament\Support\Commands\Concerns\CanValidateInput;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Savannabits\FilamentModules\FilamentModules;

class ModuleShieldGenerateCommand extends Command
{
    use CanValidateInput;

    protected $signature = 'module:shield-generate {--context} {--module}';

    protected $description = 'Create Shield permissions inside a module';

    protected ?Module $module = null;

    protected bool $allModules = false;

    public function handle(): int
    {
        $module = $this->option('module') ?: (string) Str::of($this->askRequired('Module Name (e.g. \'Sales\'. Type \'all\' to generate for all modules.)', 'module', app('modules')->getUsedNow() ?? 'all'));
        $contextInput = $this->option('context');
        if ($module) {
            if (strtolower($module) === 'all') {
                $this->module = null;
                $this->allModules = true;
            } else {
                $this->module = app('modules')->findOrFail($module);
                $this->allModules = false;
            }
        }

        if ($this->module && ! $this->allModules && $contextInput) {
            $context = Str::of($contextInput)->kebab()->prepend('-')->prepend($this->module->getLowerName())->toString();
            $this->generateShield($context);
        } else {
            if ($this->module && ! $this->allModules) {
                $contexts = FilamentModules::getModuleContexts($this->module->getLowerName());
            } else {
                $contexts = collect(Filament::getContexts())->keys();
            }
            $contexts->each(function ($context) {
                $this->generateShield($context);
            });
        }

        return self::SUCCESS;
    }

    private function generateShield(string $context): void
    {
        $this->line("Generating Shield permissions for $context:");
        if (Str::of($context)->kebab()->toString() !== 'filament') {
            \FilamentShield::configurePermissionIdentifierUsing(
                fn ($resource) => Str::of($resource::getModel())
                    ->replace('\\', '::')
                    ->lower()
                    ->toString()
            );
        }
        Filament::forContext($context, function () {
            $this->call('shield:generate', ['--all' => true]);
        });
    }
}
