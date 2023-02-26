<?php

namespace Savannabits\FilamentModules\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;

class ApplyContext
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $context)
    {
        Filament::setContext($context);

        return $next($request);
    }
}
