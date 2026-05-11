<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OrganizationFeeController;
use App\Http\Controllers\MemberProfileController;
use App\Http\Controllers\ForumController;

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
    Route::post('/members/enroll', [OrganizationMemberController::class, 'enroll']);
    Route::get('/profiles/{userId}', [MemberProfileController::class, 'show']);
    Route::middleware('member_or_admin')->group(function () {
        Route::get('/forum/posts', [ForumController::class, 'index']);
        Route::get('/forum/posts/{postId}', [ForumController::class, 'show']);
        Route::post('/forum/posts', [ForumController::class, 'storePost']);
        Route::post('/forum/posts/{postId}/comments', [ForumController::class, 'storeComment']);
        Route::patch('/forum/comments/{commentId}', [ForumController::class, 'updateComment']);
        Route::delete('/forum/comments/{commentId}', [ForumController::class, 'destroyComment']);
    });
    Route::get('/members/profile', [MemberProfileController::class, 'me']);
    Route::patch('/members/profile', [MemberProfileController::class, 'update']);
    Route::post('/members/profile/avatar', [MemberProfileController::class, 'updateAvatar']);
    Route::get('/organization-fee/me', [OrganizationFeeController::class, 'me']);
    Route::post('/organization-fee/me/submit', [OrganizationFeeController::class, 'submit']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/members/pending', [OrganizationMemberController::class, 'index']);
        Route::patch('/members/{id}/approve', [OrganizationMemberController::class, 'approve']);
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/organization-fee/overview', [OrganizationFeeController::class, 'adminOverview']);
        Route::patch('/organization-fee/submissions/{id}/review', [OrganizationFeeController::class, 'review']);
        
        // Users CRUD
        Route::apiResource('users', UserController::class);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        
        // Roles CRUD
        Route::apiResource('roles', RoleController::class);
        
        // Teachers CRUD
        Route::apiResource('teachers', TeacherController::class);
    });
});
