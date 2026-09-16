<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the user dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $today = Carbon::today();

        $totalUsers = User::count();
        $newUsersCount = User::whereDate('created_at', $today)->count();

        return view('dashboard', compact(
            'totalUsers',
            'newUsersCount',
        ));
    }
}
