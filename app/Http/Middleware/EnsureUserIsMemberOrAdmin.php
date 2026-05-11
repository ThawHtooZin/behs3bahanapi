<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMemberOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $isAdmin = (int) $user->role_id === 1;
        $isMember = strtolower((string) ($user->role?->slug ?? '')) === 'member'
            || strtolower((string) ($user->role?->name ?? '')) === 'member';

        if (!$isAdmin && !$isMember) {
            return response()->json([
                'message' => 'Only members and admins can access forum.',
            ], 403);
        }

        return $next($request);
    }
}
