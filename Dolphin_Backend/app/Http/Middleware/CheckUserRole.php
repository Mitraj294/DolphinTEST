<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckUserRole
{
    
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        
        $user = Auth::user();

        
        if (!$user->hasAnyRole(...$roles)) {
            return response()->json(['message' => 'Unauthorized. You do not have the required permission.'], 403);
        }

        return $next($request);
    }
}
