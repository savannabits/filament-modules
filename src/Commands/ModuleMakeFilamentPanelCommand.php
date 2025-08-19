<?php

namespace Coolsam\Modules\Commands;

use Coolsam\Modules\Commands\FileGenerators\ModulePanelProviderClassGenerator;
use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Filament\Commands\MakePanelCommand;
use Filament\Facades\Filament;
use Filament\Support\Commands\Concerns\CanGeneratePanels;
use Filament\Support\Commands\Concerns\CanManipulateFiles;
use Filament\Support\Commands\Exceptions\FailureCommandOutput;
use Illuminate\Console\Concerns\PromptsForMissingInput;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModuleMakeFilamentPanelCommand extends MakePanelCommand
{
    use GeneratesModularFiles;
    use CanGeneratePanels;
    use CanManipulateFiles;

    protected $name = "module:make:filament-panel";
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
        ];
    }

    public function handle(): int
    {
        try {
            $this->ensureModuleArgument();
            $this->generatePanel(
                id: $this->argument('id'),
                placeholderId: 'app',
                isForced: $this->option('force'),
            );
        } catch (FailureCommandOutput) {
            return static::FAILURE;
        }

        return static::SUCCESS;
    }

    protected function ensureModuleArgument(): void
    {
        if (! $this->argument('module')) {
            $module = select("Please select the module to create the panel in:", \Module::allEnabled());
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

        $basename = (string) str($id)
            ->studly()
            ->append('PanelProvider');

        $path = $module->appPath(
            (string) str($basename)
                ->prepend('Providers/Filament/')
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
        ]));

        $hasBootstrapProvidersFile = file_exists($bootstrapProvidersPath = App::getBootstrapProvidersPath());

        if ($hasBootstrapProvidersFile) {
            ServiceProvider::addProviderToBootstrapFile(
                $fqn,
                $bootstrapProvidersPath,
            );
        } else {
            $appConfig = file_get_contents(config_path('app.php'));

            if (! Str::contains($appConfig, "{$fqn}::class")) {
                file_put_contents(config_path('app.php'), str_replace(
                    app()->getNamespace() . 'Providers\\RouteServiceProvider::class,',
                    "{$fqn}::class," . PHP_EOL . '        ' . app()->getNamespace() . 'Providers\\RouteServiceProvider::class,',
                    $appConfig,
                ));
            }
        }

        $this->components->info("Filament panel [{$path}] created successfully.");

        if ($hasBootstrapProvidersFile) {
            $this->components->warn("We've attempted to register the {$basename} in your [bootstrap/providers.php] file. If you get an error while trying to access your panel then this process has probably failed. You can manually register the service provider by adding it to the array.");
        } else {
            $this->components->warn("We've attempted to register the {$basename} in your [config/app.php] file as a service provider.  If you get an error while trying to access your panel then this process has probably failed. You can manually register the service provider by adding it to the [providers] array.");
        }
    }
}