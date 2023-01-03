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
     * @param  Request  $request
     * @param  Closure  $next
     * @param $context
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $context)
    {
        Filament::setContext($context);

        return $next($request);
    }
}
