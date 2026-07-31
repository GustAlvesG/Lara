<?php

namespace App\Http\Middleware;

use App\Models\Aviso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avisos de leitura obrigatória: enquanto houver algum pendente, qualquer
 * navegação do usuário é desviada para a tela de ciência.
 */
class EnsureMandatoryAvisosAcknowledged
{
    /**
     * Rotas que continuam acessíveis com aviso pendente — a própria tela de
     * ciência, o POST que a confirma e a saída do sistema. Sem isso o desvio
     * viraria laço infinito.
     */
    private const ALLOWED_ROUTES = [
        'avisos.pending',
        'avisos.acknowledge',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$this->shouldIntercept($request)) {
            return $next($request);
        }

        $pending = Aviso::mandatoryPendingFor($user)->exists();

        if (!$pending) {
            return $next($request);
        }

        // Guarda o destino original para devolver o usuário ao fim da leitura.
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('avisos.pending');
    }

    /**
     * Só navegação de tela: POST/PUT/DELETE em andamento e chamadas de fundo
     * (polling de notificações, por exemplo) não são desviados.
     */
    private function shouldIntercept(Request $request): bool
    {
        return $request->isMethod('GET')
            && !$request->ajax()
            && !$request->expectsJson()
            && !in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true);
    }
}
