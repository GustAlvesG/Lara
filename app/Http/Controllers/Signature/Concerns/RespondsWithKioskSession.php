<?php

namespace App\Http\Controllers\Signature\Concerns;

use App\Http\Middleware\EnsureSignatureKioskSession;
use App\Models\SignatureRequest;
use Illuminate\Support\Facades\Cookie;

/**
 * A resposta de sessão do tablet e o cookie `lara_sign` — comuns ao quiosque
 * do balcão (QuiosqueController) e ao autoatendimento do Termo de Menores
 * (MinorTermKioskController), que abrem a MESMA sessão por caminhos
 * diferentes (QR lido / documento gerado no próprio tablet).
 *
 * Quem usa precisa ter `$this->signingData` (SignatureSigningDataService).
 */
trait RespondsWithKioskSession
{
    /**
     * O que o tablet precisa saber sobre o atendimento em curso.
     *
     * Repare no que NÃO vai: o CPF cadastrado (a conferência é feita no
     * servidor) e qualquer dado de contato. A tela mostra o nome porque é o
     * que confirma à pessoa que o documento é dela.
     *
     * @return array<string, mixed>
     */
    protected function sessionPayload(SignatureRequest $solicitacao): array
    {
        $signatario = $solicitacao->signer;
        $documento = $signatario->document;
        $modelo = $documento->template;

        return [
            'document' => [
                'id' => $documento->id,
                'title' => $documento->title,
                // O hash na URL faz o tablet buscar o arquivo de novo depois
                // que as respostas do formulário refazem o documento.
                'pdf_url' => route('quiosque.pdf', $documento) . '?v=' . substr((string) $documento->original_sha256, 0, 12),
            ],
            // As perguntas que o modelo faz a quem assina — null quando não há
            // nenhuma, ou quando alguém já assinou e o texto não muda mais.
            'form' => $this->signingData->form($documento),
            'signer' => [
                'name' => $signatario->name,
                'role' => $signatario->capacityLabel(),
                /*
                 | Só se HÁ e-mail, nunca QUAL. A tela usa isto para decidir se
                 | oferece a via por e-mail; mostrar o endereço seria expor
                 | dado de contato num tablet de balcão, que qualquer um
                 | olhando de lado enxerga.
                 */
                'has_email' => $signatario->email !== null && $signatario->email !== '',
            ],
            'rules' => [
                'identity_check' => $modelo->identity_check,
                'requires_photo' => (bool) $modelo->requires_photo,
                'requires_initials' => (bool) $modelo->requires_initials,
                // Já conferida antes de o documento existir — o CPF completo
                // digitado no autoatendimento do Termo de Menores. O tablet
                // pula a etapa de identidade.
                'identity_confirmed' => $solicitacao->identity_confirmed_at !== null,
            ],
            'session' => [
                'remaining_seconds' => $solicitacao->secondsToSessionEnd(),
                'warning_seconds' => (int) config('signature.session_warning_seconds', 60),
            ],
            // Hora do servidor, para a tela não depender do relógio do tablet.
            'server_time' => now()->toIso8601String(),
        ];
    }

    /**
     * O cookie da sessão do tablet.
     *
     * httpOnly para que nenhum script da página o leia; SameSite=Strict para
     * que ele não viaje em requisição vinda de outro site; sem `expires`, para
     * morrer junto com a aba. `Secure` acompanha a configuração de sessão do
     * app ou o esquema da requisição — em produção, com HTTPS (obrigatório
     * para a câmera), ele é sempre seguro.
     */
    protected function sessionCookie(string $value): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie()->make(
            name: EnsureSignatureKioskSession::COOKIE,
            value: $value,
            minutes: (int) config('signature.session_ttl_minutes', 15),
            path: $this->cookiePath(),
            domain: null,
            secure: (bool) config('session.secure', false) || request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }

    protected function forgetCookie(): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::forget(EnsureSignatureKioskSession::COOKIE, $this->cookiePath());
    }

    /**
     * O cookie só viaja nas requisições da tela do tablet. O caminho sai da
     * ROTA, e não de um texto fixo: se o endereço mudar e o caminho do cookie
     * ficar para trás, o tablet abre a sessão e a perde na requisição
     * seguinte — sem erro nenhum que explique.
     */
    protected function cookiePath(): string
    {
        return parse_url(route('quiosque.index'), PHP_URL_PATH) ?: '/';
    }
}
