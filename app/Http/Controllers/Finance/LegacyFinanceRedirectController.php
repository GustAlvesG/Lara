<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Os endereços de antes do módulo Financeiro (13/11/2026) — /payments,
 * /torneios/financeiro, /ingressos/financeiro, /ingressos/caixa,
 * /freelancer-services/financeiro — levam ao endereço novo, com o mesmo
 * filtro: favorito e link de e-mail antigo continuam funcionando.
 *
 * A rota diz o destino no default `to`, com os parâmetros dela entre
 * chaves (`/financeiro/torneios/{tournament}`); `{rest}` é o resto do
 * caminho, quando a rota antiga pega tudo abaixo dela.
 */
class LegacyFinanceRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $route = $request->route();
        $target = (string) $route->defaults['to'];

        foreach ($route->parameters() as $name => $value) {
            if ($name === 'to') {
                continue;
            }
            $target = str_replace('{' . $name . '}', (string) $value, $target);
        }

        $target = rtrim(preg_replace('/\{[a-z]+\}/', '', $target), '/');
        $query = $request->getQueryString();

        return redirect()->to(url($target) . ($query ? '?' . $query : ''), 301);
    }
}
