<?php

use Coolsam\Modules\Resource;
use Filament\Resources\Resource as FilamentResource;

test('resource base class extends filament resource without a conflicting import name', function () {
    expect(class_exists(Resource::class))->toBeTrue();
    expect(is_subclass_of(Resource::class, FilamentResource::class))->toBeTrue();

    $source = file_get_contents(dirname(__DIR__, 2) . '/src/Resource.php');

    expect($source)->toContain('use Filament\Resources\Resource as FilamentResource');
    expect($source)->not->toContain("use Filament\Resources\Resource;\n");
});

test('resource class can be loaded without redeclaration errors', function () {
    $reflection = new ReflectionClass(Resource::class);

    expect($reflection->isAbstract())->toBeTrue();
});
