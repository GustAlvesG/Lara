<?php

namespace App\Http\Middleware;

use App\Models\SignatureAuditEvent;
use App\Services\Signature\SignatureRequestService;
use App\Services\Signature\SignatureStateMachine;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sessão do tablet: resolve o cookie `lara_sign` e amarra a requisição a UM
 * documento.
 *
 * É um cookie próprio, e não a sessão web do app, por três razões:
 *
 *  - `SameSite=Strict` pode ser cravado nele sem mexer na configuração de
 *    sessão do sistema inteiro;
 *  - cancelar pelo painel mata a sessão na requisição SEGUINTE, porque o
 *    estado que vale é o do banco, não o do cookie;
 *  - o tablet não carrega nada da sessão de quem estiver logado no navegador.
 *
 * A trava central está em `enforceOwnership`: a sessão vale para a solicitação
 * que a abriu, e para mais nada. Trocar o id na URL responde 403 e vira evento
 * de auditoria — no documento CERTO, o que a sessão de fato alcança, para que
 * a tentativa não suje a trilha de um documento alheio.
 */
class EnsureSignatureKioskSession
{
    public const COOKIE = 'lara_sign';

    /** Onde a solicitação resolvida fica para o controller. */
    public const ATTRIBUTE = 'signature_request';

    public function __construct(
        private SignatureRequestService $requests,
        private SignatureStateMachine $states,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $solicitacao = $this->requests->fromSessionToken($request->cookie(self::COOKIE));

        if (!$solicitacao) {
            return $this->deny($request, 419, 'A sessão expirou. Peça ao atendente que gere um novo QR Code.');
        }

        $signatario = $solicitacao->signer;
        $documento = $signatario?->document;

        if (!$signatario || !$documento) {
            return $this->deny($request, 419, 'Atendimento não encontrado. Volte à tela de espera.');
        }

        /*
         | O atendente cancelou, o documento expirou, ou o signatário já
         | assinou por outro caminho. O tablet cai aqui na requisição seguinte
         | — é o que faz o cancelamento valer "em tempo real" sem broadcasting.
         */
        if ($motivo = $signatario->releaseBlockReason()) {
            return $this->deny($request, 409, $motivo);
        }

        if (!$this->enforceOwnership($request, $documento->id)) {
            $this->states->note($documento->id, SignatureAuditEvent::EVENT_ACCESS_DENIED, [
                'signer' => $signatario->id,
                'request' => $solicitacao->id,
                'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => [
                    'documento_pedido' => $request->route('signatureDocument'),
                    'documento_da_sessao' => $documento->id,
                ],
            ]);

            return $this->deny($request, 403, 'Esta sessão não tem acesso a esse documento.');
        }

        $request->attributes->set(self::ATTRIBUTE, $solicitacao);

        return $next($request);
    }

    /**
     * A rota pede um documento? Então tem de ser o da sessão.
     *
     * Rotas sem id passam direto: elas operam sobre o documento da sessão, que
     * é o desenho mais seguro. O id existe nas rotas em que ele é natural (o
     * PDF que o PDF.js busca), e aí precisa casar.
     */
    private function enforceOwnership(Request $request, int $documentId): bool
    {
        $pedido = $request->route('signatureDocument');

        if ($pedido === null) {
            return true;
        }

        return (int) (is_object($pedido) ? $pedido->id : $pedido) === $documentId;
    }

    /**
     * Recusa. O tablet conversa por JSON; um navegador que caia aqui por
     * engano é mandado de volta à tela de espera.
     */
    private function deny(Request $request, int $status, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => $message,
                'session_ended' => true,
            ], $status);
        }

        return redirect()->route('quiosque.index')->with('error', $message);
    }
}
