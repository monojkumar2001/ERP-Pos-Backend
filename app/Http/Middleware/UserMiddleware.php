<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class UserMiddleware
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
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $slug = optional($user->role)->slug;

        // Banned only by Block role slug or inactive status — never by raw role id
        // (ACL company "Administrator" roles can share numeric ids that used to collide).
        if ($slug === 'block' || $user->status === false || $user->status === 0) {
            Auth::logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been Banned. Please contact administrator.']);
        }

        if (in_array($slug, ['super-admin', 'admin'], true)) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
