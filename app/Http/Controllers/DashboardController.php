<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('role');
        
        // Get some basic stats
        $totalUsers = User::count();
        $totalAdmins = User::where('role_id', 1)->count();
        $totalUsersRole = User::where('role_id', 2)->count();

        return response()->json([
            'user' => $user,
            'stats' => [
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_users_role' => $totalUsersRole,
            ],
        ]);
    }
}
