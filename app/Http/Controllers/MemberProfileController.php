<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class MemberProfileController extends Controller
{
    public function me(Request $request)
    {
        $member = $request->user()->member;

        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        return response()->json([
            'member' => $member,
        ]);
    }

    public function update(Request $request)
    {
        $member = $request->user()->member;
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|string|max:50',
            'address' => 'required|string|max:2000',
            'parent_name' => 'nullable|string|max:255',
            'parent_occupation' => 'nullable|string|max:255',
        ]);

        $member->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'member' => $member->fresh(),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $member = $request->user()->member;
        if (!$member) {
            return response()->json([
                'message' => 'Member profile not found.',
            ], 404);
        }

        $validated = $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:4096',
        ]);

        $member->image = $validated['image']->store('members', 'public');
        $member->save();

        return response()->json([
            'message' => 'Profile image updated successfully.',
            'member' => $member->fresh(),
        ]);
    }

    public function show(Request $request, string $userId)
    {
        $profileUser = User::with(['role:id,name,slug', 'member'])
            ->select(['id', 'name', 'email', 'role_id'])
            ->findOrFail($userId);

        return response()->json([
            'user' => $profileUser,
            'member' => $profileUser->member,
            'can_edit' => (int) $request->user()->id === (int) $profileUser->id,
        ]);
    }
}
