<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        $slug = optional(optional($user)->role)->slug;

        if ($user && in_array($slug, ['super-admin', 'admin'], true)) {
            return $next($request);
        }

        return redirect()->route('login');
    }
}
