<?php

use Coolsam\Modules\Facades\FilamentModules;
use Coolsam\Modules\Modules;
use Filament\Panel;
use Illuminate\Console\Command;

test('can resolve provider class from missing provider file', function () {
    expect(FilamentModules::resolveClassFromProviderFile('/path/does/not/exist.php'))->toBeNull();
});

test('can resolve provider class from file without namespace', function () {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'provider-without-namespace.php';
    file_put_contents($path, "<?php\n\nclass ProviderWithoutNamespace {}\n");

    expect(FilamentModules::resolveClassFromProviderFile($path))->toBeNull();

    unlink($path);
});

test('can discover module clusters from disk', function () {
    $this->createModuleCluster('Blog', 'Settings');

    $clusters = FilamentModules::getModuleClusters('Blog');

    expect($clusters)->toHaveCount(1);
    expect($clusters[0])->toBe('Modules\\Blog\\Filament\\Clusters\\Settings\\SettingsCluster');
});

test('returns empty clusters when cluster directory is missing', function () {
    $this->createTestModule('Blog');

    expect(FilamentModules::getModuleClusters('Blog'))->toBe([]);
});

test('can resolve module filament page component locations', function () {
    $this->createTestModule('Blog');

    $default = FilamentModules::getModuleFilamentPageComponentLocation('Blog');
    expect($default['namespace'])->toBe('Modules\\Blog\\Filament\\Pages');
    expect($default['viewNamespace'])->toBe('blog');
    expect(is_dir($default['path']))->toBeTrue();

    $panel = FilamentModules::getModuleFilamentPageComponentLocation('Blog', 'blog-admin');
    expect($panel['namespace'])->toBe('Modules\\Blog\\Filament\\BlogAdmin');

    $cluster = FilamentModules::getModuleFilamentPageComponentLocation('Blog', forCluster: true);
    expect($cluster['namespace'])->toBe('Modules\\Blog\\Filament\\Clusters');
});

test('package path helper resolves package directories', function () {
    expect(FilamentModules::packagePath())->toEndWith('filament-modules');
    expect(FilamentModules::packagePath('config'))->toEndWith('filament-modules' . DIRECTORY_SEPARATOR . 'config');
});

test('exec command forwards output to console command', function () {
    $command = Mockery::mock(Command::class);
    $command->shouldReceive('info')->once()->with('hello');

    app(Modules::class)->execCommand('echo hello', $command);
});

test('get module panels matches registered filament panels', function () {
    $this->createModulePanelProvider('Blog', 'AdminPanelProvider');

    $this->registerTestPanel(
        Panel::make()->id('blog-admin')->path('blog/admin'),
    );

    $panels = FilamentModules::getModulePanels('Blog');

    expect(collect($panels)->map->getId()->all())->toContain('blog-admin');
});
