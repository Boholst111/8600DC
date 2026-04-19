<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $request->user() || ! $request->user()->role) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // We assume $role parameter is a single string like 'ADMIN'
        // But let's allow comma-separated in case we need 'ADMIN,DELIVERY'
        $roles = explode('|', $role);
        
        if (! in_array($request->user()->role->name, $roles)) {
            return response()->json(['message' => 'Forbidden - Access Denied'], 403);
        }

        return $next($request);
    }
}
