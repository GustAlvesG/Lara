<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * O `throttle:N,M` do projeto, contado POR ROTA.
 *
 * O do Laravel conta por usuário (logado) ou por IP — e só: todas as rotas com
 * `throttle:N,M` dividem UM contador. Com isso, o tablet de assinatura, que
 * confere a sessão a cada 5 segundos (`throttle:120,1`), estourava o
 * `throttle:10,1` de "assinar" depois de um minuto lendo o documento, e a
 * pessoa via 429 ao salvar. A tela do atendente tinha o mesmo problema: o
 * acompanhamento do status somava no limite de gerar QR e de enviar convite.
 *
 * As rotas foram escritas como se cada uma tivesse o seu limite. Esta classe
 * faz valer o que está escrito: a chave ganha a rota (nome, ou método + URI).
 * Os limitadores nomeados (`throttle:nome`, de `RateLimiter::for`) não passam
 * por aqui e seguem com a chave que declaram.
 */
class ThrottleRequestsPerRoute extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $rota = $request->route();
        $qual = $rota?->getName() ?? ($request->method() . ' ' . $rota?->uri());

        return parent::resolveRequestSignature($request) . '|' . sha1((string) $qual);
    }
}
