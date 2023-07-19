<?php

namespace Savannabits\FilamentModules;

use Blade;
use Filament\Facades\Filament;
use Filament\FilamentManager;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\ForwardsCalls;
use Str;

class FilamentModules
{
    use ForwardsCalls;

    protected array $contexts = [];

    protected ?string $currentContext = null;

    public function __construct(FilamentManager $filament)
    {
        $this->contexts['filament'] = $filament;
    }

    /**
     * @return $this
     */
    public function setContext(string $context = null)
    {
        $this->currentContext = $context;

        return $this;
    }

    public function currentContext(): string
    {
        return $this->currentContext ?? 'filament';
    }

    /**
     * @return mixed
     */
    public function getContext()
    {
        return $this->contexts[$this->currentContext ?? 'filament'];
    }

    public function getContexts(): array
    {
        return $this->contexts;
    }

    /**
     * @return $this
     */
    public function addContext(string $name)
    {
        $this->contexts[$name] = new ContextManager($name);

        return $this;
    }

    /**
     * @return $this
     */
    public function forContext(string $context, callable $callback)
    {
        $currentContext = Filament::currentContext();

        Filament::setContext($context);

        $callback();

        Filament::setContext($currentContext);

        return $this;
    }

    /**
     * @return $this
     */
    public function forAllContexts(callable $callback)
    {
        $currentContext = Filament::currentContext();

        foreach ($this->contexts as $key => $context) {
            Filament::setContext($key);

            $callback();
        }

        Filament::setContext($currentContext);

        return $this;
    }

    /**
     * Dynamically handle calls into the filament instance.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $response = $this->forwardCallTo($this->getContext(), $method, $parameters);

        if ($response instanceof FilamentManager) {
            return $this;
        }

        return $response;
    }

    public static function getModuleContexts(string $module): Collection
    {
        $prefix = Str::of($module)->lower()->append('-')->toString();
        return collect(Filament::getContexts())->keys()->filter(fn($item) => Str::of($item)->contains("$prefix"));
    }

    public static function registerFilamentNavigationItem($module, $context): void
    {
        $panel = Str::of($context)->after('-')->replace('filament', 'default')->slug()->replace('-', ' ')->title()->title();
        $moduleContexts = static::getModuleContexts($module);
        $module_lower = \Module::findOrFail($module)->getLowerName();
        $can = Filament::auth()->check() && Filament::auth()->user()->can("module_{$module_lower}");
        $navItem = NavigationItem::make($context)->visible($can)->url(route($context . '.pages.dashboard'))->icon('heroicon-o-bookmark');
        if ($can) {
            Filament::registerNavigationItems([
                $moduleContexts->count() === 1 ? $navItem->label("$module Module") : $navItem->label("$panel Panel")->group("$module Module")
            ]);
        }
    }

    public static function hasAuthorizedAccess(string $context)
    {
        $module = Str::of($context)->before('-')->lower();
        return Filament::auth()->check() && Filament::auth()->user()->can('module_' . $module);
    }

    public static function renderContextNavigation($module, $context): void
    {
        Filament::registerRenderHook('sidebar.start', fn(): string => Blade::render('<div class="p-2 px-6 bg-primary-100 font-black w-full">' . "$module Module</div>"));
        Filament::registerRenderHook('sidebar.end', fn(): string => Blade::render('<a class="p-2 px-6 bg-primary-100 font-black w-full inline-flex space-x-2" href="' . route('filament.pages.dashboard') . '"><x-heroicon-o-arrow-left class="w-5"/> Main Module</a>'));
    }

    public function prepareDefaultNavigation($module, $context): void
    {
        Filament::serving(function () use ($module, $context) {
            Filament::forContext('filament', function () use ($module, $context) {
                app(FilamentModules::class)::registerFilamentNavigationItem($module, $context);
            });
            Filament::forContext($context, function () use ($module, $context) {
                app(FilamentModules::class)::renderContextNavigation($module, $context);
            });
        });
    }

}
