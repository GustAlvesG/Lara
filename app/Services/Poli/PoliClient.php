<?php

namespace App\Services\Poli;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

/**
 * Único ponto da aplicação que fala com a API v3 da Poli.
 *
 * Espelha o poli_teste_envio.py, validado contra a API real: mesmos métodos,
 * caminhos, query strings e corpos. Envio de texto e de template LIST foram
 * comprovados com entrega no aparelho em 25/09/2026; o resto (distribuir,
 * encaminhar, nota) segue a documentação e ainda não foi exercitado de
 * verdade.
 *
 * Ao contrário do PoliMessageService, este client LANÇA: RequestException em
 * resposta de erro, ConnectionException sem resposta, InvalidArgumentException
 * para telefone que não dá para normalizar. Quem não pode quebrar (o aviso da
 * portaria) usa o PoliMessageService, que traduz tudo isso em resultado.
 *
 * Novas tentativas só para conexão, 5xx e 429. Um 4xx de validação é payload
 * errado: repetir erra igual.
 */
class PoliClient
{
    private const PROVIDER = 'WHATSAPP';
    private const VERSION = 'v3';

    private const TEMPLATE_INCLUDE = 'key,status,message,interactive,account_channel';
    private const MESSAGE_INCLUDE = 'ack,direction,components,metadata';

    private ?string $channelOverride = null;

    /**
     * Mesmo client, enviando por outro canal. Sem canal, vale o do .env.
     */
    public function usandoCanal(?string $channelUuid): static
    {
        $clone = clone $this;
        $clone->channelOverride = filled($channelUuid) ? $channelUuid : null;

        return $clone;
    }

    /* ---------------------------------------------------------------------
     | Envio
     |---------------------------------------------------------------------*/

    /**
     * @param string|null $responderMsgUuid Mensagem a citar (resposta com contexto).
     */
    public function texto(string $contactUuid, string $texto, ?string $responderMsgUuid = null): array
    {
        $corpo = $this->corpoTexto($texto);

        if ($responderMsgUuid !== null) {
            $corpo['context'] = ['type' => 'message', 'message' => ['uuid' => $responderMsgUuid]];
        }

        return $this->post("/contacts/{$contactUuid}/messages", $corpo);
    }

    /**
     * Devolve a resposta inteira: com `?include=contact` ela traz o
     * `contact.uuid`, que é o que todos os outros endpoints usam.
     */
    public function textoPorTelefone(string $telefone, string $texto): array
    {
        return $this->post($this->caminhoPorTelefone($telefone), $this->corpoTexto($texto));
    }

    /**
     * Botões e listas não se montam na requisição: só existem como template
     * BUTTON/LIST cadastrado no painel da Poli, enviado pelo uuid.
     *
     * @param string[] $params Variáveis do template, na ordem.
     */
    public function template(string $contactUuid, string $templateUuid, array $params = []): array
    {
        return $this->post("/contacts/{$contactUuid}/messages", $this->corpoTemplate($templateUuid, $params));
    }

    /**
     * @param string[] $params
     */
    public function templatePorTelefone(string $telefone, string $templateUuid, array $params = []): array
    {
        return $this->post($this->caminhoPorTelefone($telefone), $this->corpoTemplate($templateUuid, $params));
    }

    /**
     * Nota interna, visível só para os atendentes. Sem canal: não sai no
     * WhatsApp. Pela documentação — ainda não exercitado de verdade.
     */
    public function nota(string $contactUuid, string $texto): array
    {
        return $this->post("/contacts/{$contactUuid}/messages", [
            'provider' => 'ANNOTATION',
            'type' => 'TEXT',
            'version' => self::VERSION,
            'components' => ['body' => ['text' => $texto]],
        ]);
    }

    /* ---------------------------------------------------------------------
     | Consulta
     |---------------------------------------------------------------------*/

    /**
     * Lista os templates da conta. O filtro aceita BUTTON, LIST, WABA e
     * QUICK_MESSAGE.
     */
    public function templates(?string $tipo = null): array
    {
        $query = ['include' => self::TEMPLATE_INCLUDE, 'per_page' => 100];

        if (filled($tipo)) {
            $query['type'] = strtoupper($tipo);
        }

        return $this->desembrulhar($this->get("/accounts/{$this->conta()}/templates", $query));
    }

    /**
     * Times (departamentos) da conta, com nome — o destino do transbordo.
     * Conferido na API real em 25/09/2026.
     */
    public function times(): array
    {
        return $this->desembrulhar($this->get("/accounts/{$this->conta()}/teams", ['include' => 'attributes']));
    }

    /**
     * Uma mensagem, com o ACK. A resposta pode vir embrulhada em `data`.
     */
    public function mensagem(string $uuid): array
    {
        return $this->desembrulhar($this->get("/messages/{$uuid}", ['include' => self::MESSAGE_INCLUDE]));
    }

    /* ---------------------------------------------------------------------
     | Atendimento
     |---------------------------------------------------------------------*/

    /**
     * Encerra a conversa. A despedida, se houver, sai antes. O /close vai SEM
     * corpo e responde 204 — o exemplo da documentação com `user_uuid` no
     * corpo é cópia do forward.
     */
    public function encerrar(string $contactUuid, ?string $despedida = null): void
    {
        if (filled($despedida)) {
            $this->texto($contactUuid, $despedida);
        }

        $this->enviar('POST', "/contacts/{$contactUuid}/close");
    }

    /** Pela documentação — ainda não exercitado de verdade. */
    public function distribuir(string $contactUuid, string $teamUuid): void
    {
        $this->post("/contacts/{$contactUuid}/distribute", ['team' => $teamUuid]);
    }

    /**
     * Passa o contato para um usuário ou aplicação, opcionalmente num time.
     * Pela documentação — ainda não exercitado de verdade.
     */
    public function encaminhar(
        string $contactUuid,
        ?string $userUuid = null,
        ?string $applicationUuid = null,
        ?string $teamUuid = null,
    ): void {
        if (blank($userUuid) && blank($applicationUuid)) {
            throw new InvalidArgumentException('Informe o usuário ou a aplicação de destino.');
        }

        $this->post("/contacts/{$contactUuid}/forward", array_filter([
            'user_uuid' => $userUuid,
            'application_uuid' => $applicationUuid,
            'team_uuid' => $teamUuid,
        ], 'filled'));
    }

    /** Pela documentação — ainda não exercitado de verdade. */
    public function marcarComoLida(string $contactUuid): void
    {
        $this->enviar('POST', "/contacts/{$contactUuid}/read");
    }

    /* ---------------------------------------------------------------------
     | Telefone
     |---------------------------------------------------------------------*/

    /**
     * Só dígitos, com DDI. Com 10 ou 11 dígitos (DDD + número) prefixa o 55;
     * o que não terminar com 12 ou 13 dígitos é recusado sem chamar a API.
     */
    public static function normalizarTelefone(string $telefone): string
    {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';

        if (in_array(strlen($digitos), [10, 11], true)) {
            $digitos = '55' . $digitos;
        }

        if (strlen($digitos) < 12 || strlen($digitos) > 13) {
            throw new InvalidArgumentException("Telefone inválido: {$telefone}");
        }

        return $digitos;
    }

    /* ---------------------------------------------------------------------
     | Montagem
     |---------------------------------------------------------------------*/

    private function corpoTexto(string $texto): array
    {
        return [
            'provider' => self::PROVIDER,
            'account_channel_uuid' => $this->canal(),
            'type' => 'TEXT',
            'version' => self::VERSION,
            'components' => ['body' => ['text' => $texto]],
        ];
    }

    private function corpoTemplate(string $templateUuid, array $params): array
    {
        $corpo = [
            'provider' => self::PROVIDER,
            'account_channel_uuid' => $this->canal(),
            'type' => 'TEMPLATE',
            'template_uuid' => $templateUuid,
            'version' => self::VERSION,
        ];

        // Sem parâmetros o corpo vai sem `components`, igual ao script
        // validado: um `components` vazio nunca foi testado.
        if ($params !== []) {
            $corpo['components'] = ['body' => ['parameters' => array_map(
                fn ($p) => ['type' => 'text', 'text' => (string) $p],
                array_values($params),
            )]];
        }

        return $corpo;
    }

    private function caminhoPorTelefone(string $telefone): string
    {
        return "/accounts/{$this->conta()}/contacts/" . self::normalizarTelefone($telefone) . '/messages?include=contact';
    }

    private function desembrulhar(array $resposta): array
    {
        return is_array($resposta['data'] ?? null) ? $resposta['data'] : $resposta;
    }

    private function conta(): string
    {
        return (string) config('poli.account_uuid');
    }

    private function canal(): string
    {
        return $this->channelOverride
            ?? (string) (config('poli.channel_uuid') ?? config('poli.default_channel_uuid'));
    }

    /* ---------------------------------------------------------------------
     | HTTP
     |---------------------------------------------------------------------*/

    private function post(string $caminho, array $corpo): array
    {
        return $this->enviar('POST', $caminho, ['json' => $corpo]);
    }

    private function get(string $caminho, array $query): array
    {
        return $this->enviar('GET', $caminho, ['query' => $query]);
    }

    /**
     * @throws RequestException|ConnectionException
     */
    private function enviar(string $metodo, string $caminho, array $opcoes = []): array
    {
        $url = rtrim((string) config('poli.base_url'), '/') . $caminho;

        /** @var Response $resposta */
        $resposta = $this->http()->send($metodo, $url, $opcoes);

        // Com uma tentativa só (retries = 0) o retry() não lança sozinho.
        $resposta->throw();

        return $resposta->json() ?? [];
    }

    private function http(): PendingRequest
    {
        $tentativas = 1 + max(0, (int) config('poli.http.retries', 2));

        return Http::withToken((string) config('poli.token'))
            ->acceptJson()
            ->connectTimeout((int) config('poli.http.connect_timeout', 10))
            ->timeout((int) config('poli.http.timeout', 20))
            // throw: true lança RequestException também quando NÃO repete —
            // é o que faz um 422 subir na primeira tentativa.
            ->retry(
                $tentativas,
                (int) config('poli.http.retry_sleep_ms', 300),
                fn (Throwable $e) => self::valeRepetir($e),
                throw: true,
            );
    }

    private static function valeRepetir(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            $status = $e->response->status();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
