<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboards do site: só o sócio com perfil `administrador`.
 *
 * Roda DEPOIS do `login_token` (JwtMiddleware), que já validou o token do
 * sócio e deixou o payload em `user`. O CPF sai do token; o perfil sai do
 * banco a cada requisição — o token não carrega perfil nenhum. Assim,
 * rebaixar alguém em Contas do site corta o acesso na hora, e os tokens
 * emitidos antes do perfil existir continuam valendo.
 *
 * A conta é a que o login usa (a ativa mais antiga do CPF). Conta desativada
 * nunca é administradora.
 *
 * A conta fica em `$request->attributes->get('member')` para o controller.
 */
class EnsureMemberSiteAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            return $next($request);
        }

        $cpf = data_get($request->input('user'), 'username');
        $member = Member::loginAccount(is_string($cpf) ? $cpf : null);

        if (! $member || ! $member->isSiteAdmin()) {
            return response()->json(['error' => 'Sem acesso aos dashboards.'], 403);
        }

        $request->attributes->set('member', $member);

        return $next($request);
    }
}
