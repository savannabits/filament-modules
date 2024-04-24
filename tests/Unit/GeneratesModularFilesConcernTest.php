<?php

// Setup for all tests
beforeEach(function () {
    $this->trait = new class extends \Illuminate\Console\Command
    {
        use Coolsam\Modules\Concerns\GeneratesModularFiles;

        public function getRelativeNamespace(): string
        {
            return 'Commands';
        }

        public function getStub()
        {
            return $this->resolveStubPath('stubs/filament-plugin.stub');
        }
    };
});

test('can generate the correct stubs path', function () {
    // include the GeneratesModularFiles trait
    expect($this->trait->getStub())
        ->toEqual(realpath(__DIR__ . '/../../src/Commands/stubs/filament-plugin.stub'));
});
