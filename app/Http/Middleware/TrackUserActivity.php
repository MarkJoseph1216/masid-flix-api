<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $user = $request->user();
            $user->updateLastSeen();
        }

        return $next($request);
    }
}