<?php

namespace App\Http\Controllers;

use App\Models\OrganizationMember;
use App\Models\Role;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
{
    public function index()
    {
        $members = OrganizationMember::with(['user.role'])
            ->latest()
            ->get();

        return response()->json([
            'organization_members' => $members,
        ]);
    }

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nrc_number' => 'required|string|max:255|unique:organization_members,nrc_number',
            'gender' => 'required|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'dob' => 'required|date|before:today',
            'address' => 'required|string|max:2000',
            'contact_number' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'agreed_to_rules' => 'accepted',
        ]);

        $user = $request->user();

        if (OrganizationMember::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are already enrolled as an organization member.',
            ], 409);
        }

        $data = $validated;
        $data['user_id'] = $user->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('organization-members', 'public');
        }

        $organizationMember = OrganizationMember::create($data);
        $user->load('role');

        return response()->json([
            'message' => 'Enrollment submitted successfully. Waiting for admin approval.',
            'organization_member' => $organizationMember,
            'user' => $user,
        ], 201);
    }

    public function approve(Request $request, string $id)
    {
        $organizationMember = OrganizationMember::with('user')->findOrFail($id);

        if ($organizationMember->status === 'approved') {
            return response()->json([
                'message' => 'This enrollment is already approved.',
            ], 409);
        }

        $memberRole = Role::firstOrCreate(
            ['slug' => 'member'],
            [
                'name' => 'Member',
                'description' => 'Organization member access',
                'has_dashboard_access' => false,
            ]
        );

        $organizationMember->status = 'approved';
        $organizationMember->approved_at = now();
        $organizationMember->approved_by = $request->user()->id;
        $organizationMember->save();

        $organizationMember->user->role_id = $memberRole->id;
        $organizationMember->user->save();
        $organizationMember->user->load('role');

        return response()->json([
            'message' => 'Enrollment approved successfully.',
            'organization_member' => $organizationMember->fresh(),
            'user' => $organizationMember->user,
        ]);
    }
}
