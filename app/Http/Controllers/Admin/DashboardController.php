<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
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
