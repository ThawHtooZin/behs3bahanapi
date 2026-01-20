<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Get all roles
     */
    public function index()
    {
        $roles = Role::orderBy('id', 'asc')->get();
        return response()->json(['roles' => $roles]);
    }

    /**
     * Get a single role
     */
    public function show($id)
    {
        $role = Role::with('users')->findOrFail($id);
        return response()->json(['role' => $role]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'slug' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'has_dashboard_access' => 'boolean',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'has_dashboard_access' => $request->has_dashboard_access ?? false,
        ]);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role,
        ], 201);
    }

    /**
     * Update a role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Prevent modifying default roles (id 1 and 2)
        if (in_array($role->id, [1, 2])) {
            return response()->json([
                'message' => 'Default roles (Admin and User) cannot be modified',
            ], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'has_dashboard_access' => 'boolean',
        ]);

        if ($request->has('name')) {
            $role->name = $request->name;
        }
        if ($request->has('slug')) {
            $role->slug = $request->slug;
        }
        if ($request->has('description')) {
            $role->description = $request->description;
        }
        if ($request->has('has_dashboard_access')) {
            $role->has_dashboard_access = $request->has_dashboard_access;
        }

        $role->save();

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role,
        ]);
    }

    /**
     * Delete a role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting default roles (id 1 and 2)
        if (in_array($role->id, [1, 2])) {
            return response()->json([
                'message' => 'Default roles (Admin and User) cannot be deleted',
            ], 403);
        }

        // Check if any users have this role
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role. There are users assigned to this role.',
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }
}
