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
use App\Http\Controllers\RecordController;
use App\Http\Controllers\RecordUploadController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\AnnouncementController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public routes
Route::get('/public/teachers', [TeacherController::class, 'publicIndex']);
Route::get('/public/news', [NewsController::class, 'publicIndex']);
Route::get('/public/news/{id}', [NewsController::class, 'publicShow']);
Route::get('/public/announcements', [AnnouncementController::class, 'publicIndex']);
Route::get('/public/announcements/{id}', [AnnouncementController::class, 'publicShow']);

Route::middleware('auth:sanctum')->group(function () {
Route::get('/user', function (Request $request) {
        $user = $request->user()->load('role');
        return $user;
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/members/enroll', [OrganizationMemberController::class, 'enroll']);
    Route::get('/profiles/{userId}', [MemberProfileController::class, 'show']);
    // Forum: any authenticated user can view (and counts as a view);
    // only members/admins can create posts, comment, edit or delete.
    Route::get('/forum/posts', [ForumController::class, 'index']);
    Route::get('/forum/posts/{postId}', [ForumController::class, 'show']);
    Route::middleware('member_or_admin')->group(function () {
        Route::post('/forum/posts', [ForumController::class, 'storePost']);
        Route::post('/forum/posts/{postId}/comments', [ForumController::class, 'storeComment']);
        Route::patch('/forum/comments/{commentId}', [ForumController::class, 'updateComment']);
        Route::delete('/forum/comments/{commentId}', [ForumController::class, 'destroyComment']);
    });

    // Records (မှတ်တမ်းများ): authenticated users can view + react; members/admins can post.
    Route::get('/records', [RecordController::class, 'index']);
    Route::middleware('member_or_admin')->group(function () {
        // Chunked upload session endpoints — must come before /records/{id}.
        Route::post('/records/uploads/chunk', [RecordUploadController::class, 'chunk']);
        Route::delete('/records/uploads/{uploadId}', [RecordUploadController::class, 'cancel']);
        Route::post('/records', [RecordController::class, 'store']);
        Route::post('/records/{id}', [RecordController::class, 'update']);
        Route::delete('/records/{id}', [RecordController::class, 'destroy']);
    });
    Route::get('/records/{id}', [RecordController::class, 'show']);
    Route::get('/records/{id}/reactions', [RecordController::class, 'reactions']);
    Route::post('/records/{id}/reactions', [RecordController::class, 'react']);
    Route::delete('/records/{id}/reactions', [RecordController::class, 'unreact']);
    Route::get('/members/profile', [MemberProfileController::class, 'me']);
    Route::patch('/members/profile', [MemberProfileController::class, 'update']);
    Route::post('/members/profile/avatar', [MemberProfileController::class, 'updateAvatar']);
    Route::get('/organization-fee/me', [OrganizationFeeController::class, 'me']);
    Route::post('/organization-fee/me/submit', [OrganizationFeeController::class, 'submit']);
    Route::post('/organization-fee/me/submit-prepay', [OrganizationFeeController::class, 'submitPrepay']);

    // Admin only routes
    Route::middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/members/pending', [OrganizationMemberController::class, 'index']);
        Route::patch('/members/{id}/approve', [OrganizationMemberController::class, 'approve']);
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/organization-fee/overview', [OrganizationFeeController::class, 'adminOverview']);
        Route::patch('/organization-fee/submissions/{id}/review', [OrganizationFeeController::class, 'review']);
        Route::post('/organization-fee/submissions/batch-review', [OrganizationFeeController::class, 'batchReview']);
        Route::get('/organization-fee/settings', [OrganizationFeeController::class, 'getSettings']);
        Route::put('/organization-fee/settings', [OrganizationFeeController::class, 'updateSettings']);
        
        // Users CRUD
        Route::apiResource('users', UserController::class);
        Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
        
        // Roles CRUD
        Route::apiResource('roles', RoleController::class);
        
        // Teachers CRUD
        Route::apiResource('teachers', TeacherController::class);

        // Site content (news & announcements; image upload only)
        Route::get('/news', [NewsController::class, 'index']);
        Route::post('/news', [NewsController::class, 'store']);
        Route::get('/news/{id}', [NewsController::class, 'show']);
        Route::patch('/news/{id}', [NewsController::class, 'update']);
        Route::delete('/news/{id}', [NewsController::class, 'destroy']);

        Route::get('/announcements', [AnnouncementController::class, 'index']);
        Route::post('/announcements', [AnnouncementController::class, 'store']);
        Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
        Route::patch('/announcements/{id}', [AnnouncementController::class, 'update']);
        Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);
    });
});
