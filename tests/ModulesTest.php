<?php

it('can test', function () {
    expect(true)->toBeTrue();
});

// test that a module can be generated successfully
it('can generate a module', function () {
    $this->artisan('module:make', ['name' => ['Example']])
        ->expectsOutput('Module created successfully.')
        ->assertExitCode(0);
});
