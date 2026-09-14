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
	$token = (string) config('services.api.token');
	if ($request->getMethod() === 'OPTIONS') {
        	return $next($request);
	}
        // Sem token configurado nada passa: senão um "Bearer " puro seria aceito.
        if ($token === '') {
            return response()->json(['message' => 'Invalid API Token'], 401);
        }
        // hash_equals: comparação de tempo constante, e o header recebido nunca
        // volta no corpo da resposta (vazava o token para log de proxy).
        if (! hash_equals("Bearer " . $token, (string) $request->header('Authorization'))) {
            return response()->json(['message' => 'Invalid API Token'], 401);
        }
        return $next($request);
    }
}
