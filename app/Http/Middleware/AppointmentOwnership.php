<?php

namespace App\Http\Middleware;

use Closure;

class AppointmentOwnership
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $condition = auth()->user()->appointments->pluck('id')->contains($request->appointment->id);

        if (! $condition) {
            abort(401, 'Permission denied.');
        }

        return $next($request);
    }
}
