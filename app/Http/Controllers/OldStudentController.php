<?php

namespace App\Http\Controllers;

use App\Models\OldStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OldStudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $oldStudents = OldStudent::orderBy('id', 'asc')->get();
        return response()->json(['old_students' => $oldStudents]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|max:255',
            'nrc_number' => 'nullable|string|max:255',
            'partner_name' => 'nullable|string|max:255',
            'job' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('photo');

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoPath = $photo->store('old-students', 'public');
            $data['photo'] = $photoPath;
        }

        $oldStudent = OldStudent::create($data);

        return response()->json([
            'message' => 'Old student created successfully',
            'old_student' => $oldStudent,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $oldStudent = OldStudent::findOrFail($id);
        return response()->json(['old_student' => $oldStudent]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $oldStudent = OldStudent::findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'dob' => 'nullable|date',
            'gender' => 'nullable|string|max:255',
            'nrc_number' => 'nullable|string|max:255',
            'partner_name' => 'nullable|string|max:255',
            'job' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data = $request->except('photo');

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($oldStudent->photo) {
                Storage::disk('public')->delete($oldStudent->photo);
            }
            $photo = $request->file('photo');
            $photoPath = $photo->store('old-students', 'public');
            $data['photo'] = $photoPath;
        }

        $oldStudent->update($data);

        return response()->json([
            'message' => 'Old student updated successfully',
            'old_student' => $oldStudent,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $oldStudent = OldStudent::findOrFail($id);
        
        // Delete photo if exists
        if ($oldStudent->photo) {
            Storage::disk('public')->delete($oldStudent->photo);
        }
        
        $oldStudent->delete();

        return response()->json([
            'message' => 'Old student deleted successfully',
        ]);
    }
}
