<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Where to redirect users after login (resolved per role slug).
     *
     * @return string
     */
    public function redirectTo()
    {
        $slug = optional(optional(Auth::user())->role)->slug;

        if (in_array($slug, ['super-admin', 'admin'], true)) {
            return route('admin.dashboard');
        }

        return route('user.dashboard');
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        $slug = optional($user->role)->slug;

        if ($slug === 'block' || $user->status === false || $user->status === 0) {
            Auth::logout();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been Banned. Please contact administrator.']);
        }

        return null;
    }
}
