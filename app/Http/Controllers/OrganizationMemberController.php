<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Role;
use Illuminate\Http\Request;

class OrganizationMemberController extends Controller
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

    public function enroll(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nrc_number' => 'required|string|max:255|unique:members,nrc_number',
            'gender' => 'required|string|max:50',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'dob' => 'required|date|before:today',
            'address' => 'required|string|max:2000',
            'contact_number' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'parent_name' => 'required|string|max:255',
            'parent_occupation' => 'required|string|max:255',
            'agreed_to_rules' => 'accepted',
            'family_members' => 'nullable|string',
        ]);

        $user = $request->user();

        if (Member::where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You are already enrolled as an organization member.',
            ], 409);
        }

        $data = $validated;
        $data['user_id'] = $user->id;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('members', 'public');
        }

        $familyMembers = [];
        if (!empty($validated['family_members'])) {
            $decoded = json_decode($validated['family_members'], true);
            if (!is_array($decoded)) {
                return response()->json([
                    'message' => 'Family members format is invalid.',
                ], 422);
            }
            $familyMembers = collect($decoded)
                ->filter(fn ($row) => is_array($row))
                ->map(fn ($row) => [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'relation' => trim((string) ($row['relation'] ?? '')),
                    'dob' => !empty($row['dob']) ? $row['dob'] : null,
                    'nrc_number' => trim((string) ($row['nrcNumber'] ?? '')) ?: null,
                ])
                ->filter(fn ($row) => $row['name'] !== '')
                ->values()
                ->all();
        }

        unset($data['family_members']);

        $organizationMember = Member::create($data);
        if (!empty($familyMembers)) {
            $organizationMember->familyMembers()->createMany($familyMembers);
        }
        $user->load('role');

        return response()->json([
            'message' => 'Enrollment submitted successfully. Waiting for admin approval.',
            'member' => $organizationMember->load('familyMembers'),
            'user' => $user,
        ], 201);
    }

    public function approve(Request $request, string $id)
    {
        $organizationMember = Member::with('user')->findOrFail($id);

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
            'member' => $organizationMember->fresh(),
            'user' => $organizationMember->user,
        ]);
    }
}
