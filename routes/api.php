<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\OldStudentController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public routes
Route::get('/public/teachers', [TeacherController::class, 'publicIndex']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load('role');
        return $user;
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        
        // Users CRUD
        Route::apiResource('users', UserController::class);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        
        // Roles CRUD
        Route::apiResource('roles', RoleController::class);
        
        // Teachers CRUD
        Route::apiResource('teachers', TeacherController::class);
        
        // Old Students CRUD
        Route::apiResource('old-students', OldStudentController::class);
    });
});
