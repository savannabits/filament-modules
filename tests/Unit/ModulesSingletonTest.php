<?php

use Coolsam\Modules\Facades\FilamentModules;

test('can convert path to namespace correctly', function () {
    $path = config('modules.paths.modules') . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'TestServiceProvider.php';
    $namespace = FilamentModules::convertPathToNamespace($path);
    expect($namespace)->toBe($expected = 'Modules\\Providers\\TestServiceProvider', "Expected $expected Instead got " . $namespace);
});

test('can convert windows style module paths to namespaces', function () {
    $base = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, config('modules.paths.modules'));
    $path = $base . DIRECTORY_SEPARATOR . 'Blog' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR . 'BlogServiceProvider.php';
    $namespace = FilamentModules::convertPathToNamespace($path);

    expect($namespace)->toBe('Modules\\Blog\\Providers\\BlogServiceProvider');
});
