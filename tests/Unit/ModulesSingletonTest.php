<?php

use Coolsam\Modules\Facades\FilamentModules;

test('can convert path to namespace correctly', function () {
    $path = DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';

    $path = config('modules.paths.modules') . '/app/Providers/TestServiceProvider.php';
    $namespace = FilamentModules::convertPathToNamespace($path);
    expect($namespace)->toBe($expected = 'Modules\\Providers\\TestServiceProvider', "Expected $expected Instead got " . $namespace);
});
