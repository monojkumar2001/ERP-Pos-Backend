<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (!Auth::guard($guard)->check()) {
            return $next($request);
        }

        $slug = optional(Auth::user()->role)->slug;

        if (in_array($slug, ['super-admin', 'admin'], true)) {
            return redirect()->route('admin.dashboard');
        }

        if ($slug === 'block') {
            Auth::logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been Banned. Please contact administrator.']);
        }

        if ($slug === 'user' || $slug === 'bayer') {
            return redirect()->route($slug === 'bayer' ? 'bayer.dashboard' : 'user.dashboard');
        }

        // Company ACL roles (administrator, cashier, …) — send to user area
        return redirect()->route('user.dashboard');
    }
}
