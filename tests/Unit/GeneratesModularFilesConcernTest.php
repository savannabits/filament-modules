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
            $d = DIRECTORY_SEPARATOR;

            return $this->resolveStubPath('stubs/filament-plugin.stub');
        }
    };
});

test('can generate the correct stubs path', function () {
    // include the GeneratesModularFiles trait
    $d = DIRECTORY_SEPARATOR;
    expect($this->trait->getStub())
        ->toEqual(realpath(__DIR__."{$d}..{$d}..{$d}src{$d}Commands{$d}stubs{$d}filament-plugin.stub"));
});
