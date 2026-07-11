<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Rolemiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // 1. Verify user context is active and authenticated
        if(!$request ->user()){
            return response()->json([
                'status' => 'error',
                'messsage'=> 'Unauthenticated access request rejected',
            ], 401);
        }
        // 2. Validate current state corresponds to authorized parameter
        if($request->user()->role !== $role){
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized entry. Access privilege mismatch'
            ], 403);
        }
        return $next($request);

    }
}
