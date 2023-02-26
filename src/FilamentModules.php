<?php

namespace Savannabits\FilamentModules;

use Filament\Facades\Filament;
use Filament\FilamentManager;
use Illuminate\Support\Traits\ForwardsCalls;

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
}
