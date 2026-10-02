<?php

namespace App\Http\Middleware;

use App\Models\Aviso;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        if (!$this->hasPending($user)) {
            return $next($request);
        }

        // Guarda o destino original para devolver o usuário ao fim da leitura.
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('avisos.pending');
    }

    /**
     * Roda em toda tela do sistema, então não pode ser o que derruba o
     * sistema: se a consulta falhar (migration da leitura obrigatória ainda
     * não aplicada, banco fora), a navegação segue e o erro vai para o log —
     * uma vez por processo, para não inundar.
     */
    private function hasPending($user): bool
    {
        static $warned = false;

        try {
            // Quase sempre não há nenhum aviso obrigatório ativo: uma consulta
            // barata resolve, sem carregar os setores da pessoa.
            if (! Aviso::where('mandatory', true)->active()->exists()) {
                return false;
            }

            return Aviso::mandatoryPendingFor($user)->exists();
        } catch (\Throwable $e) {
            if (!$warned) {
                $warned = true;
                Log::warning('Leitura obrigatória de avisos indisponível: ' . $e->getMessage());
            }

            return false;
        }
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
