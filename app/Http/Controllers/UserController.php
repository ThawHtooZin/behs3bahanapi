<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with their roles
     */
    public function index(Request $request)
    {
        $users = User::with('role')
            ->select('id', 'name', 'email', 'role_id', 'created_at')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Get a single user
     */
    public function show($id)
    {
        $user = User::with('role')->findOrFail($id);
        return response()->json(['user' => $user]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        $user->load('role');

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'sometimes|string|min:8',
            'role_id' => 'sometimes|required|exists:roles,id',
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }
        if ($request->has('role_id')) {
            // Prevent changing your own role
            if ($user->id === $request->user()->id && $request->role_id !== $user->role_id) {
                return response()->json([
                    'message' => 'You cannot change your own role',
                ], 403);
            }
            $user->role_id = $request->role_id;
        }

        $user->save();
        $user->load('role');

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user,
        ]);
    }

    /**
     * Delete a user
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Update user role (legacy method for backward compatibility)
     */
    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($id);

        // Prevent changing your own role
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own role',
            ], 403);
        }

        $user->role_id = $request->role_id;
        $user->save();
        $user->load('role');

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => $user,
        ]);
    }
}
