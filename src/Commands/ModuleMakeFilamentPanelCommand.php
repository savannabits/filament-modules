<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Commands\FileGenerators\ModulePanelProviderClassGenerator;
use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Filament\Commands\MakePanelCommand;
use Filament\Support\Commands\Concerns\CanGeneratePanels;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModuleMakeFilamentPanelCommand extends MakePanelCommand
{
    use CanGeneratePanels;
    use CanManipulateFiles;
    use GeneratesModularFiles;

    protected $name = 'module:make:filament-panel';

    protected $description = 'Create a new Filament panel class in the specified module';

    /**
     * @var array<string>
     */
    protected $aliases = [
        'module:filament:make-panel',
        'module:filament:panel',
    ];

    /**
     * @return array<InputArgument>
     */
    protected function getArguments(): array
    {
        return [
            new InputArgument(
                name: 'id',
                mode: InputArgument::OPTIONAL,
                description: 'The ID of the panel',
            ),
            new InputArgument(
                name: 'module',
                mode: InputArgument::OPTIONAL,
                description: 'The module to create the panel in',
            ),
        ];
    }

    /**
     * @return array<InputOption>
     */
    protected function getOptions(): array
    {
        return [
            new InputOption(
                name: 'force',
                shortcut: 'F',
                mode: InputOption::VALUE_NONE,
                description: 'Overwrite the contents of the files if they already exist',
            ),
            new InputOption(
                name: 'label',
                shortcut: null,
                mode: InputOption::VALUE_OPTIONAL,
                description: 'The navigation label for the panel',
            ),
        ];
    }

    public function handle(): int
    {
        try {
            $this->ensureModuleArgument();
            $this->generatePanel(
                id: $this->argument('id'),
                placeholderId: 'default',
                isForced: $this->option('force'),
            );
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        return static::SUCCESS;
    }

    protected function ensureNavigationLabelOption(): void
    {
        if (! $this->option('label')) {
            $label = text(
                label: 'What is the navigation label for the panel?',
                placeholder: Str::title($this->argument('id') ?? $this->getModule()->getName() . ' App'),
                required: true,
                validate: fn (string $value) => empty($value) ? 'The navigation label cannot be empty.' : null,
                hint: 'This is used in the navigation to identify the panel.',
            );
            if (empty($label)) {
                $this->components->error('Navigation label cannot be empty. Aborting panel creation.');
                exit(1);
            }
            $this->input->setOption('label', $label);
        }
    }

    protected function ensureModuleArgument(): void
    {
        if (! $this->argument('module')) {
            $module = select('Please select the module to create the panel in:', \Module::allEnabled());
            if (! $module) {
                $this->components->error('No module selected. Aborting panel creation.');
                exit(1);
            }
            $this->input->setArgument('module', $module);
        }
    }

    protected function getRelativeNamespace(): string
    {
        return 'Providers\\Filament';
    }

    /**
     * @throws FailureCommandOutput
     */
    public function generatePanel(?string $id = null, string $defaultId = '', string $placeholderId = '', bool $isForced = false): void
    {
        $module = $this->getModule();
        $this->components->info("Creating Filament panel in module [{$module->getName()}]...");
        $id = Str::lcfirst($id ?? text(
            label: 'What is the panel\'s ID?',
            placeholder: $placeholderId,
            required: true,
            validate: fn (string $value) => match (true) {
                preg_match('/^[a-zA-Z].*/', $value) !== false => null,
                default => 'The ID must start with a letter, and not a number or special character.',
            },
            hint: 'It must be unique to any others you have, and is used to reference the panel in your code.',
        ));
        if (empty($id)) {
            $this->components->error('Panel ID cannot be empty. Aborting panel creation.');
            exit(1);
        }
        $this->ensureNavigationLabelOption();

        $basename = (string) str($id)
            ->studly()
            ->append('PanelProvider');

        $path = $module->appPath(
            (string) str($basename)
                ->prepend('/Providers/Filament/')
                ->replace('\\', '/')
                ->append('.php'),
        );

        if (! $isForced && $this->checkForCollision([$path])) {
            throw new FailureCommandOutput;
        }

        $fqn = $module->appNamespace("Providers\\Filament\\{$basename}");

        $this->writeFile($path, app(ModulePanelProviderClassGenerator::class, [
            'fqn' => $fqn,
            'id' => $id,
            'moduleName' => $module->getName(),
            'navigationLabel' => $this->option('label'),
        ]));

        $this->components->info("Filament panel [{$path}] created successfully.");
    }
}
