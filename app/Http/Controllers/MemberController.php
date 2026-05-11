<?php

namespace App\Http\Controllers;

use App\Models\Member;

class MemberController extends Controller
{
    public function index()
    {
        $members = Member::with(['user.role', 'familyMembers'])
            ->latest()
            ->get();

        return response()->json([
            'members' => $members,
        ]);
    }
}
