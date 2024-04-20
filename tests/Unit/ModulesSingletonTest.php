<?php

use Coolsam\Modules\Facades\FilamentModules;

test('can convert path to namespace correctly', function () {
    $path = config('modules.paths.modules') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'TestServiceProvider.php';
    $namespace = FilamentModules::convertPathToNamespace($path);
    expect($namespace)->toBe($expected = 'Modules\\Providers\\TestServiceProvider', "Expected $expected Instead got " . $namespace);
});
