<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class APIToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
	$apitoken = "Bearer " . config('services.api.token');
	if ($request->getMethod() === 'OPTIONS') {
        	return $next($request);
	}
        if ($request->header('Authorization') !== $apitoken) {
            return response()->json(['message' => 'Invalid API Tokenaaaa', 'token' => $request->header('Authorization')], 401);
        }
        return $next($request);
    }
}
