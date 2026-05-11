<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    /**
     * Empty strings from multipart forms become null so nullable columns and email rules behave.
     */
    private function normalizeNullableTeacherRequest(Request $request): void
    {
        $keys = ['phone', 'email', 'address', 'subject', 'position', 'from_year', 'to_year'];
        $merge = [];
        foreach ($keys as $key) {
            if (! $request->exists($key)) {
                continue;
            }
            $value = $request->input($key);
            if ($value === '' || $value === null) {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /**
     * Clear stale photo paths that no longer exist on disk.
     */
    private function sanitizeTeachers($teachers)
    {
        return $teachers->map(function ($teacher) {
            if ($teacher->photo && !Storage::disk('public')->exists($teacher->photo)) {
                $teacher->photo = null;
            }

            return $teacher;
        });
    }

    /**
     * Display a listing of the resource (public).
     */
    public function publicIndex()
    {
        $teachers = $this->sanitizeTeachers(Teacher::latest()->get());
        return response()->json(['teachers' => $teachers]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teachers = $this->sanitizeTeachers(Teacher::latest()->get());
        return response()->json(['teachers' => $teachers]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->normalizeNullableTeacherRequest($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'position' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'from_year' => 'nullable|integer',
            'to_year' => 'nullable|integer',
        ]);

        $data = collect($validated)->except(['photo'])->all();

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $photoPath = $photo->store('teachers', 'public');
            $data['photo'] = $photoPath;
        }

        $teacher = Teacher::create($data);

        return response()->json([
            'message' => 'Teacher created successfully',
            'teacher' => $teacher,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $teacher = Teacher::findOrFail($id);
        return response()->json(['teacher' => $teacher]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $teacher = Teacher::findOrFail($id);

        $this->normalizeNullableTeacherRequest($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'position' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'from_year' => 'nullable|integer',
            'to_year' => 'nullable|integer',
        ]);

        $data = collect($validated)->except(['photo'])->all();

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($teacher->photo) {
                Storage::disk('public')->delete($teacher->photo);
            }
            $photo = $request->file('photo');
            $photoPath = $photo->store('teachers', 'public');
            $data['photo'] = $photoPath;
        }

        $teacher->update($data);

        return response()->json([
            'message' => 'Teacher updated successfully',
            'teacher' => $teacher,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $teacher = Teacher::findOrFail($id);
        
        // Delete photo if exists
        if ($teacher->photo) {
            Storage::disk('public')->delete($teacher->photo);
        }
        
        $teacher->delete();

        return response()->json([
            'message' => 'Teacher deleted successfully',
        ]);
    }
}
