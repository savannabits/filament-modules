<?php

namespace Coolsam\Modules\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Coolsam\Modules\ModulesServiceProvider;
use Coolsam\Modules\Tests\Support\CreatesTestModules;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Nwidart\Modules\LaravelModulesServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;

class TestCase extends Orchestra
{
    use CreatesTestModules;
    use WithWorkbench;

    protected function setUp(): void
    {
        $this->resetModulesDirectory();

        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Coolsam\\Modules\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LaravelModulesServiceProvider::class,
            ModulesServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $modulesPath = $app->basePath('Modules');

        if (! is_dir($modulesPath)) {
            mkdir($modulesPath, 0755, true);
        }

        config()->set('modules.paths.modules', $modulesPath);
        config()->set('modules.namespace', 'Modules');
        config()->set('modules.paths.app_folder', 'app');
        config()->set('modules.activators.file.statuses-file', $app->basePath('modules_statuses.json'));
    }
}
