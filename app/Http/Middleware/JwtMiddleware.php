<?php

namespace App\Http\Middleware;

use App\Providers\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    protected $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;

    }



    public function handle(Request $request, Closure $next)
    {
	if ($request->getMethod() === 'OPTIONS') {
	    return $next($request);
	}
        // Obter o token no cabeçalho "login-token"
        $token = $request->header('Session');


        if (!$token) {
            return response()->json(['error' => 'Not Found Login Token'], 400);
        }

        try {
            // Validar o token
            $payload = $this->jwtService->validateToken($token);
            $request->merge(['user' => $payload]); // Adicionar payload à requisição
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid Login Token'], 401);
        }

        return $next($request);
    }}
