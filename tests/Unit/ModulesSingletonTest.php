<?php

use Coolsam\Modules\Facades\FilamentModules;

test('can convert path to namespace correctly', function () {
    $path = DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'AppServiceProvider.php';
    $namespace = FilamentModules::convertPathToNamespace($path);
    expect($namespace)->toBe($expected = 'Modules\\Providers\\AppServiceProvider', "Expected $expected Instead got " . $namespace);
});
