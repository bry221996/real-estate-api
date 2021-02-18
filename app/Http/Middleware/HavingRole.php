<?php

namespace App\Http\Middleware;

use Closure;

class HavingRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, ...$roles)
    {
        $userRoles = auth()->user()->roles->pluck('name');

        if ($userRoles->intersect($roles)->isEmpty()) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
