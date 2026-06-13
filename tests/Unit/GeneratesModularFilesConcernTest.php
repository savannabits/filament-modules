<?php

use Coolsam\Modules\Concerns\GeneratesModularFiles;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;

beforeEach(function () {
    $this->command = new class(app(Filesystem::class)) extends GeneratorCommand
    {
        use GeneratesModularFiles;

        protected $name = 'test:make-modular';

        protected $description = 'Test modular generator';

        protected $type = 'Filament Plugin';

        public function getRelativeNamespace(): string
        {
            return 'Filament\\Resources';
        }

        protected function getStub(): string
        {
            return $this->resolveStubPath('stubs/filament-plugin.stub');
        }

        public function exposeGetStub(): string
        {
            return $this->getStub();
        }

        protected function stubReplacements(): array
        {
            return [
                'moduleStudlyName' => $this->getModule()->getStudlyName(),
                'pluginId' => 'blog',
            ];
        }

        public function exposeRootNamespace(): string
        {
            return $this->rootNamespace();
        }

        public function exposeDefaultNamespace(string $rootNamespace): string
        {
            return $this->getDefaultNamespace($rootNamespace);
        }

        public function exposeViewPath(string $path = ''): string
        {
            return $this->viewPath($path);
        }

        public function exposePath(string $name): string
        {
            return $this->getPath($name);
        }

        public function exposePossibleModels(): array
        {
            return $this->possibleModels();
        }

        public function exposeBuildClass(string $name): string
        {
            return $this->buildClass($name);
        }

        public function exposePrompts(): array
        {
            return $this->promptForMissingArgumentsUsing();
        }

        public function exposeArguments(): array
        {
            return $this->getArguments();
        }
    };

    $this->command->setLaravel($this->app);

    $input = Mockery::mock(InputInterface::class);
    $input->shouldReceive('getArgument')->with('module')->andReturn('Blog');
    $input->shouldReceive('getArgument')->with('name')->andReturn('PostResource');
    $this->command->setInput($input);
});

test('can generate the correct stubs path', function () {
    $d = DIRECTORY_SEPARATOR;

    expect($this->command->exposeGetStub())
        ->toEqual(realpath(__DIR__ . "{$d}..{$d}..{$d}src{$d}Commands{$d}stubs{$d}filament-plugin.stub"));
});

test('modular generator resolves module namespace and paths', function () {
    $this->createTestModule('Blog');

    expect($this->command->getModule()->getName())->toBe('Blog');
    expect($this->command->exposeRootNamespace())->toBe('Modules\\Blog\\');
    expect($this->command->exposeDefaultNamespace('Modules\\Blog\\'))
        ->toBe('Modules\\Blog\\Filament\\Resources');
    expect($this->command->exposeViewPath('pages'))->toEndWith('resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'pages');
    expect($this->command->exposePath('Modules\\Blog\\Filament\\Resources\\PostResource'))
        ->toEndWith('Blog' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Filament' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'PostResource.php');
});

test('modular generator can list possible models', function () {
    $this->createModuleModel('Blog', 'Post');

    expect($this->command->exposePossibleModels())->toBe(['Post']);
    expect($this->command->possibleFqnModels())->toBe(['Modules\\Blog\\Models\\Post']);
});

test('modular generator builds class content from stub replacements', function () {
    $this->createTestModule('Blog');

    $input = Mockery::mock(InputInterface::class);
    $input->shouldReceive('getArgument')->with('module')->andReturn('Blog');
    $input->shouldReceive('getArgument')->with('name')->andReturn('AccessPlugin');
    $this->command->setInput($input);

    $class = $this->command->exposeBuildClass('Modules\\Blog\\Filament\\AccessPlugin');

    expect($class)->toContain('AccessPlugin');
    expect($class)->toContain('Blog');
});

test('modular generator exposes prompt metadata for missing arguments', function () {
    $prompts = $this->command->exposePrompts();

    expect($prompts)->toHaveKeys(['name', 'module']);
    expect($prompts['name'][0])->toContain('filament plugin');
});

test('modular generator merges module argument into command definition', function () {
    $arguments = $this->command->exposeArguments();

    expect(collect($arguments)->pluck(0))->toContain('module');
});

test('modular generator exposes default stub replacements', function () {
    $command = new class(app(Filesystem::class)) extends GeneratorCommand
    {
        use GeneratesModularFiles;

        protected $name = 'test:default-replacements';

        protected $description = 'Test default stub replacements';

        protected $type = 'Filament Plugin';

        protected function getRelativeNamespace(): string
        {
            return 'Filament';
        }

        protected function getStub(): string
        {
            return '';
        }

        public function exposeStubReplacements(): array
        {
            return $this->stubReplacements();
        }

        public function exposePromptForType(string $type): string
        {
            $this->type = $type;

            return $this->promptForMissingArgumentsUsing()['name'][1];
        }
    };

    expect($command->exposeStubReplacements())->toBe([]);
    expect($command->exposePromptForType('Model'))->toBe('E.g. Flight');
    expect($command->exposePromptForType('Unknown'))->toBe('');
});
