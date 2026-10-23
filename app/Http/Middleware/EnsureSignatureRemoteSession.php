<?php

namespace App\Http\Middleware;

use App\Services\Signature\RemoteSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sessão de quem abriu o link remoto: o segredo devolvido pela abertura,
 * no header `X-Signature-Session`.
 *
 * É o equivalente do cookie `lara_sign` do tablet (EnsureSignatureKioskSession),
 * em header porque quem chama é o servidor do site, e não um navegador. O site
 * guarda o segredo num cookie httpOnly do PRÓPRIO domínio e o repassa aqui.
 *
 * A sessão do tablet não abre esta API, nem o contrário
 * (RemoteSignatureService::fromSessionToken). Cancelar pelo painel, revogar o
 * link ou o signatário assinar pelo tablet derruba a sessão na requisição
 * seguinte — o estado que vale é o do banco.
 */
class EnsureSignatureRemoteSession
{
    public const HEADER = 'X-Signature-Session';

    public function __construct(private RemoteSignatureService $remote)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $solicitacao = $this->remote->fromSessionToken($request->header(self::HEADER));

        if (!$solicitacao || !$solicitacao->signer?->document) {
            return $this->deny(419, 'A sessão terminou. Abra de novo o link do e-mail.');
        }

        if ($motivo = $solicitacao->signer->releaseBlockReason()) {
            return $this->deny(409, $motivo);
        }

        $request->attributes->set(EnsureSignatureKioskSession::ATTRIBUTE, $solicitacao);

        return $next($request);
    }

    private function deny(int $status, string $message): Response
    {
        return response()->json([
            'error' => $message,
            'reason' => 'session_ended',
            'session_ended' => true,
        ], $status);
    }
}
