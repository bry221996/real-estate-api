<?php

namespace App\Http\Middleware;

use Closure;

class PropertyOwnership
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
        if (auth()->id() != optional($request->property)->created_by) {
            return response([
                'message' => 'Permission denied.', 
            ], 401);
        }

        return $next($request);
    }
}
