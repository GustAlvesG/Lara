<?php

namespace App\Services\Sicoob;

use App\Exceptions\Sicoob\SicoobAuthenticationException;
use App\Exceptions\Sicoob\SicoobException;
use App\Exceptions\Sicoob\SicoobInsufficientFundsException;
use App\Exceptions\Sicoob\SicoobPayeeMismatchException;
use App\Exceptions\Sicoob\SicoobPaymentNotFoundException;
use App\Exceptions\Sicoob\SicoobPaymentOutcomeUnknownException;
use App\Exceptions\Sicoob\SicoobPaymentRejectedException;
use App\Models\PixPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Envio de Pix pela API "Pix Pagamentos" do Sicoob.
 *
 * O envio são DUAS chamadas, e a diferença entre elas é a coisa mais
 * importante deste arquivo:
 *
 *   1. POST /pagamentos            → iniciação DICT. Consulta a chave, devolve
 *                                    o titular e RESERVA um endToEndId.
 *                                    NÃO MOVE DINHEIRO. Repetir é inofensivo.
 *   2. POST /pagamentos/confirmacao → efetivação. É AQUI que o dinheiro sai.
 *                                    Repetir é transferir de novo.
 *   3. GET  /pagamentos/{e2eid}    → estado atual da transação.
 *
 * Toda a segurança do fluxo se apoia nisso: entre 1 e 2 dá para conferir o
 * titular e o saldo com a transação já identificada e nada ainda gasto; e
 * depois de 2, quando a resposta não chega, a saída NUNCA é reenviar — é
 * consultar pelo endToEndId, que já está guardado.
 *
 * Contrato conferido na especificação oficial "Pagamentos PIX 2.0.24.6"
 * (developers.sicoob.com.br). Ver docs/funcionalidades/pix-sicoob.md.
 */
class SicoobPixPagamentoService
{
    /** Estados que o Sicoob devolve em `RetornoPagamento.estado`. */
    const ESTADO_FINALIZADO = 'FINALIZADO';
    const ESTADO_EM_PROCESSAMENTO = 'EM_PROCESSAMENTO';
    const ESTADO_REJEITADO = 'REJEITADO';
    const ESTADO_NAO_REALIZADO = 'NAO_REALIZADO';

    public function __construct(
        private SicoobAuthService $auth,
        private SicoobContaCorrenteService $contaCorrente,
    ) {
    }

    /* =====================================================================
     | Orquestração
     |=====================================================================*/

    /**
     * Executa uma tentativa de pagamento do início ao fim, gravando cada
     * transição na própria linha.
     *
     * A linha SEMPRE termina em um estado que descreve com honestidade o que
     * aconteceu com o dinheiro — inclusive `unknown`, que é o jeito de dizer
     * "não sabemos", em vez de chutar sucesso ou fracasso.
     *
     * @throws SicoobException
     */
    public function enviar(PixPayment $payment): PixPayment
    {
        if (!in_array($payment->status, [PixPayment::STATUS_PENDING, PixPayment::STATUS_INITIATED], true)) {
            throw new SicoobException(
                "Pagamento #{$payment->id} não está em um estado que permita envio (status: {$payment->status}).",
                ['pix_payment_id' => $payment->id, 'status' => $payment->status]
            );
        }

        $valor = (float) $payment->amount;

        // Barreiras locais primeiro: são grátis e param o erro mais bobo e mais
        // caro (valor errado) antes de qualquer contato com o banco.
        $this->validarValor($valor);

        $this->contaCorrente->assertSaldoSuficiente($valor);

        // --- Passo 1: iniciação DICT (dinheiro parado) --------------------
        if ($payment->end_to_end_id === null) {
            $iniciacao = $this->iniciar($payment->pix_key);

            $endToEndId = $iniciacao['endToEndId'] ?? null;

            if (!is_string($endToEndId) || $endToEndId === '') {
                throw new SicoobException(
                    'O Sicoob iniciou o pagamento sem devolver o endToEndId.',
                    ['pix_payment_id' => $payment->id]
                );
            }

            $payment->forceFill([
                'end_to_end_id' => $endToEndId,
                'payee_document' => $iniciacao['proprietario']['identificador'] ?? null,
                'payee_name' => $iniciacao['proprietario']['nome'] ?? null,
                'payee_key_type' => $iniciacao['tipo'] ?? null,
                'status' => PixPayment::STATUS_INITIATED,
                'initiated_at' => now(),
                'response_payload' => $iniciacao,
            ])->save();
        }

        // Fora do `if` de propósito: quando o job reentra num pagamento que já
        // estava `initiated` (worker morto entre os dois passos), o titular
        // gravado precisa ser conferido de novo antes de confirmar. É uma
        // comparação local, e a alternativa é confirmar sem ter checado.
        $this->assertTitularConfere($payment);

        // --- Passo 2: confirmação (o dinheiro sai) ------------------------
        return $this->confirmarPagamento($payment);
    }

    /**
     * Passo 2 isolado, com o tratamento de desfecho.
     *
     * @throws SicoobException
     */
    protected function confirmarPagamento(PixPayment $payment): PixPayment
    {
        $body = $this->montarPayloadConfirmacao($payment);

        $payment->forceFill(['request_payload' => $body])->save();

        try {
            $response = $this->post('/pagamentos/confirmacao', $body);
        } catch (ConnectionException $e) {
            // A requisição subiu e a resposta não voltou. O Pix pode ter sido
            // processado — este é o caso que proíbe retry.
            return $this->marcarDesfechoDesconhecido($payment, 'timeout/conexão: ' . $e->getMessage(), $e);
        }

        // 5xx: tratado como desconhecido de propósito. Um 503 quase sempre
        // significa "não processei", mas "quase sempre" não serve para dinheiro.
        // A reconciliação resolve sozinha em poucos minutos, consultando o
        // endToEndId; o custo de ser conservador aqui é baixo, e o de estar
        // errado é pagar duas vezes.
        if ($response->serverError()) {
            return $this->marcarDesfechoDesconhecido(
                $payment,
                'HTTP ' . $response->status() . ' na confirmação',
                null,
                $response
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            // O gateway barrou antes de processar: nada saiu.
            $this->auth->forgetToken(config('sicoob.scopes.pix'));

            $this->marcarFalha($payment, 'autenticação recusada na confirmação', $response);

            throw new SicoobAuthenticationException(
                'O Sicoob recusou a autenticação ao confirmar o pagamento (HTTP ' . $response->status() . ').',
                $this->contextoDe($payment, $response)
            );
        }

        if ($response->failed()) {
            // 4xx é recusa com resposta: a requisição foi analisada e negada,
            // e a conta não foi debitada.
            return $this->marcarRejeicaoPorErro($payment, $response);
        }

        return $this->aplicarRetorno($payment, $response->json() ?? [], confirmando: true);
    }

    /**
     * Consulta o estado real de um pagamento no banco e atualiza a linha.
     *
     * É o único caminho legítimo para tirar um pagamento de `unknown`. Também
     * é o que fecha os `sent`, que mudam de estado no banco sem nos avisar.
     *
     * @throws SicoobException
     */
    public function reconciliar(PixPayment $payment): PixPayment
    {
        if ($payment->end_to_end_id === null) {
            // Sem endToEndId a confirmação nunca foi enviada: nada saiu, e a
            // tentativa pode ser refeita.
            $this->marcarFalha($payment, 'sem endToEndId — a iniciação não chegou a concluir');

            return $payment;
        }

        try {
            $retorno = $this->consultar($payment->end_to_end_id);
        } catch (SicoobPaymentNotFoundException $e) {
            // O banco não conhece este id. Se NÓS nunca chegamos a confirmar,
            // isso encerra o caso: o id foi apenas reservado na iniciação e
            // nenhum pagamento existiu. Libera nova tentativa.
            //
            // Se já confirmamos, 404 não prova nada — pode ser latência de
            // liquidação — e o pagamento continua em aberto até o banco
            // responder ou o prazo estourar e pedir conferência humana.
            if ($payment->confirmed_at === null && $payment->status === PixPayment::STATUS_INITIATED) {
                $this->marcarFalha($payment, 'o banco não conhece o endToEndId e a confirmação nunca foi enviada');

                return $payment;
            }

            throw $e;
        }

        $payment->forceFill(['last_checked_at' => now()])->save();

        return $this->aplicarRetorno($payment, $retorno, confirmando: false);
    }

    /* =====================================================================
     | Chamadas cruas à API
     |=====================================================================*/

    /**
     * Passo 1 — iniciação por chave DICT. Não move dinheiro: consulta a chave,
     * devolve o titular e reserva o endToEndId.
     *
     * @return array<string, mixed>
     *
     * @throws SicoobException
     */
    public function iniciar(string $chave): array
    {
        if (trim($chave) === '') {
            throw new InvalidArgumentException('Chave Pix de destino vazia.');
        }

        try {
            $response = $this->post('/pagamentos', ['chave' => trim($chave)]);
        } catch (ConnectionException $e) {
            // Iniciação é idempotente do ponto de vista do dinheiro: repetir
            // não custa nada, então uma falha aqui é apenas uma falha.
            throw new SicoobException(
                'Não foi possível iniciar o pagamento no Sicoob (falha de conexão).',
                ['chave' => $this->mascararChave($chave)],
                $e
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $this->auth->forgetToken(config('sicoob.scopes.pix'));

            // O corpo do 401 é a única pista de POR QUE foi recusado: o gateway
            // distingue credencial inválida de escopo ausente, e sem isso os dois
            // casos chegam aqui como o mesmo "401" mudo.
            throw new SicoobAuthenticationException(
                'O Sicoob recusou a autenticação na iniciação do pagamento (HTTP ' . $response->status() . ').',
                [
                    'status' => $response->status(),
                    'resposta' => $this->corpoDoErro($response),
                ]
            );
        }

        if ($response->failed()) {
            throw new SicoobPaymentRejectedException(
                'O Sicoob recusou a iniciação do pagamento (HTTP ' . $response->status() . ').',
                $this->detalheDoErro($response),
                [
                    'status' => $response->status(),
                    'chave' => $this->mascararChave($chave),
                    'violacoes' => $response->json('violacoes'),
                ]
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Passo 3 — estado atual da transação, pelo identificador fim a fim.
     *
     * @return array<string, mixed>
     *
     * @throws SicoobException
     */
    public function consultar(string $endToEndId): array
    {
        try {
            $response = $this->auth
                ->client(config('sicoob.scopes.pix'), (bool) config('sicoob.pix.enviar_client_id', true))
                ->get($this->url('/pagamentos/' . $endToEndId));
        } catch (ConnectionException $e) {
            throw new SicoobException(
                'Não foi possível consultar o pagamento no Sicoob.',
                ['end_to_end_id' => $endToEndId],
                $e
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $this->auth->forgetToken(config('sicoob.scopes.pix'));

            throw new SicoobAuthenticationException(
                'O Sicoob recusou a autenticação na consulta do pagamento.',
                ['end_to_end_id' => $endToEndId, 'status' => $response->status()]
            );
        }

        // 404 tem leitura própria, que depende de já termos confirmado ou não —
        // ver SicoobPaymentNotFoundException e `reconciliar()`.
        if ($response->status() === 404) {
            throw new SicoobPaymentNotFoundException(
                'O Sicoob não encontrou o pagamento ' . $endToEndId . '.',
                ['end_to_end_id' => $endToEndId]
            );
        }

        if ($response->failed()) {
            throw new SicoobException(
                'Consulta do pagamento no Sicoob falhou (HTTP ' . $response->status() . ').',
                ['end_to_end_id' => $endToEndId, 'status' => $response->status()]
            );
        }

        return $response->json() ?? [];
    }

    /* =====================================================================
     | Regras de valor e de titular
     |=====================================================================*/

    /**
     * Formato que a API exige em `valor`: string com VÍRGULA decimal e no
     * máximo duas casas (`^[0-9]{1,18}([,][0-9]{1,2})?$`).
     *
     * Não é float, não é centavos e não aceita separador de milhar — 1234.5
     * vira "1234,50", nunca "1.234,50" nem "123450".
     */
    public static function formatarValor(float $valor): string
    {
        return number_format($valor, 2, ',', '');
    }

    /**
     * Última barreira antes de o dinheiro sair. Vale para qualquer caminho de
     * envio, inclusive um reprocessamento manual de fila.
     *
     * @throws InvalidArgumentException
     */
    public function validarValor(float $valor): void
    {
        if ($valor <= 0) {
            throw new InvalidArgumentException(
                'Valor de Pix inválido: ' . self::formatarValor($valor) . '. O valor precisa ser positivo.'
            );
        }

        $max = (float) config('sicoob.pix.max_amount', 0);

        if ($max > 0 && $valor > $max) {
            throw new InvalidArgumentException(
                'Valor de R$ ' . self::formatarValor($valor) . ' acima do teto configurado de R$ '
                . self::formatarValor($max) . ' (SICOOB_PIX_MAX_AMOUNT). Nenhum valor foi transferido.'
            );
        }

        // A API arredondaria em silêncio; um centavo perdido por transferência
        // é o tipo de diferença que só aparece na conciliação contábil.
        if (round($valor, 2) !== round($valor, 4)) {
            throw new InvalidArgumentException(
                'Valor de Pix com mais de duas casas decimais: ' . $valor . '.'
            );
        }
    }

    /**
     * O dono da chave no DICT tem de ser o freelancer que estamos pagando.
     *
     * Esta é a checagem que impede o dinheiro de ir para um terceiro quando a
     * chave do cadastro está errada, foi digitada errada ou mudou de titular.
     * Roda entre a iniciação e a confirmação, com a transação já identificada
     * e nada ainda gasto.
     *
     * @throws SicoobPayeeMismatchException
     */
    protected function assertTitularConfere(PixPayment $payment): void
    {
        if (!config('sicoob.pix.validar_titular', true)) {
            return;
        }

        $esperado = $this->apenasDigitos($payment->freelancer?->cpf);
        $recebido = $this->apenasDigitos($payment->payee_document);

        // Sem um dos lados não há comparação a fazer — e recusar por ausência
        // de dado travaria pagamentos legítimos de cadastro antigo.
        if ($esperado === '' || $recebido === '') {
            Log::channel('sicoob')->warning('Sicoob: titular da chave não pôde ser conferido', [
                'pix_payment_id' => $payment->id,
                'tem_cpf_cadastro' => $esperado !== '',
                'tem_titular_dict' => $recebido !== '',
            ]);

            return;
        }

        if ($esperado === $recebido) {
            return;
        }

        // CPF de pessoa física pagando em conta PJ: os 8 primeiros dígitos do
        // CNPJ não têm relação com o CPF, então não há o que reconciliar aqui.
        $this->marcarFalha($payment, 'titular da chave diverge do cadastro do freelancer');

        Log::channel('sicoob')->error('Sicoob: chave Pix pertence a outro titular — pagamento abortado', [
            'pix_payment_id' => $payment->id,
            'freelancer_id' => $payment->freelancer_id,
            'end_to_end_id' => $payment->end_to_end_id,
            'titular_dict' => $payment->payee_name,
        ]);

        throw new SicoobPayeeMismatchException(
            expectedDocument: $esperado,
            actualDocument: $recebido,
            actualName: $payment->payee_name,
            context: ['pix_payment_id' => $payment->id],
        );
    }

    /* =====================================================================
     | Montagem e leitura de payloads
     |=====================================================================*/

    /**
     * Corpo de `POST /pagamentos/confirmacao`.
     *
     * `destino` fica DE FORA de propósito: com `meioIniciacao: CHAVE` o Sicoob
     * já resolveu o destino na iniciação, e mandar o objeto só cria uma chance
     * de divergência entre o que enviamos e o que o DICT devolveu.
     *
     * `origem` só vai quando configurada — em produção o Sicoob usa a conta
     * vinculada ao certificado.
     *
     * @return array<string, mixed>
     */
    protected function montarPayloadConfirmacao(PixPayment $payment): array
    {
        $body = [
            'endToEndId' => $payment->end_to_end_id,
            'valor' => self::formatarValor((float) $payment->amount),
            'meioIniciacao' => 'CHAVE',
        ];

        if (filled($payment->description)) {
            $body['descricao'] = mb_substr($payment->description, 0, 140);
        }

        $origem = (array) config('sicoob.pix.origem', []);

        if ($origem !== []) {
            $body['origem'] = $origem;
        }

        return $body;
    }

    /**
     * Traduz um `RetornoPagamento` para o estado da nossa linha.
     *
     * @param  array<string, mixed>  $retorno
     * @param  bool  $confirmando  true quando vem da confirmação, false da consulta
     *
     * @throws SicoobPaymentRejectedException
     */
    protected function aplicarRetorno(PixPayment $payment, array $retorno, bool $confirmando): PixPayment
    {
        $estado = $this->normalizarEstado($retorno['estado'] ?? null);
        $detalhe = $retorno['detalheRejeicao'] ?? null;

        $dados = [
            'bank_state' => $retorno['estado'] ?? null,
            'response_payload' => $retorno,
            'rejection_detail' => $detalhe,
        ];

        if ($confirmando) {
            $dados['confirmed_at'] = now();
        }

        switch ($estado) {
            case self::ESTADO_FINALIZADO:
                $dados['status'] = PixPayment::STATUS_FINALIZED;
                $dados['finalized_at'] = now();
                break;

            case self::ESTADO_REJEITADO:
            case self::ESTADO_NAO_REALIZADO:
                $dados['status'] = PixPayment::STATUS_REJECTED;
                break;

            case self::ESTADO_EM_PROCESSAMENTO:
                $dados['status'] = PixPayment::STATUS_SENT;
                break;

            default:
                // A confirmação foi aceita (HTTP 2xx) mas o estado veio vazio ou
                // desconhecido. Tratar como "em processamento" faria a
                // reconciliação buscar a verdade; tratar como sucesso seria
                // inventá-la. Vai para `sent`, e a consulta decide.
                $dados['status'] = $confirmando ? PixPayment::STATUS_SENT : $payment->status;
                break;
        }

        $payment->forceFill($dados)->save();

        Log::channel('sicoob')->info('Sicoob: estado do Pix atualizado', [
            'pix_payment_id' => $payment->id,
            'freelancer_service_id' => $payment->freelancer_service_id,
            'end_to_end_id' => $payment->end_to_end_id,
            'valor' => (float) $payment->amount,
            'estado_banco' => $retorno['estado'] ?? null,
            'status' => $payment->status,
            'origem_da_leitura' => $confirmando ? 'confirmacao' : 'consulta',
            'ambiente' => config('sicoob.environment'),
        ]);

        if ($payment->isRejected()) {
            throw new SicoobPaymentRejectedException(
                'O Sicoob recusou o pagamento' . ($detalhe ? ': ' . $detalhe : '.'),
                $detalhe,
                ['pix_payment_id' => $payment->id, 'end_to_end_id' => $payment->end_to_end_id]
            );
        }

        return $payment;
    }

    /**
     * O Sicoob devolve `NÃO_REALIZADO` com til. Comparar a string acentuada
     * direto funciona até alguém salvar o arquivo em outra codificação — a
     * normalização evita esse tipo de bug silencioso, em que um pagamento
     * recusado passaria por "estado desconhecido".
     */
    protected function normalizarEstado(?string $estado): ?string
    {
        if ($estado === null) {
            return null;
        }

        $semAcento = strtr(mb_strtoupper(trim($estado)), [
            'Ã' => 'A', 'Á' => 'A', 'À' => 'A', 'Â' => 'A',
            'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O',
            'Õ' => 'O', 'Ô' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ]);

        return str_replace([' ', '-'], '_', $semAcento);
    }

    /* =====================================================================
     | Transições de falha
     |=====================================================================*/

    /**
     * Estado `unknown`: a confirmação saiu e o desfecho não voltou.
     *
     * @throws SicoobPaymentOutcomeUnknownException
     */
    protected function marcarDesfechoDesconhecido(
        PixPayment $payment,
        string $motivo,
        ?ConnectionException $e = null,
        ?Response $response = null,
    ): PixPayment {
        $payment->forceFill([
            'status' => PixPayment::STATUS_UNKNOWN,
            'confirmed_at' => now(),
            'rejection_detail' => 'Desfecho desconhecido: ' . $motivo,
            'response_payload' => $response?->json(),
        ])->save();

        // Nível `critical`: é o único estado do fluxo que pode exigir olho
        // humano no extrato do banco.
        Log::channel('sicoob')->critical('Sicoob: DESFECHO DESCONHECIDO — o Pix pode ter sido processado', [
            'pix_payment_id' => $payment->id,
            'freelancer_service_id' => $payment->freelancer_service_id,
            'end_to_end_id' => $payment->end_to_end_id,
            'valor' => (float) $payment->amount,
            'motivo' => $motivo,
            'ambiente' => config('sicoob.environment'),
            'acao' => 'NÃO reenviar. A reconciliação vai consultar o endToEndId no banco.',
        ]);

        throw new SicoobPaymentOutcomeUnknownException(
            'A confirmação do Pix foi enviada e o desfecho não pôde ser determinado (' . $motivo . ').',
            ['pix_payment_id' => $payment->id, 'end_to_end_id' => $payment->end_to_end_id],
            $e
        );
    }

    /** Estado `failed`: parou antes de confirmar, nada saiu da conta. */
    protected function marcarFalha(PixPayment $payment, string $motivo, ?Response $response = null): void
    {
        $payment->forceFill([
            'status' => PixPayment::STATUS_FAILED,
            'rejection_detail' => $motivo,
            'response_payload' => $response?->json() ?? $payment->response_payload,
        ])->save();
    }

    /** 4xx na confirmação: recusa analisada pelo banco, sem débito. */
    protected function marcarRejeicaoPorErro(PixPayment $payment, Response $response): PixPayment
    {
        $detalhe = $this->detalheDoErro($response);

        $payment->forceFill([
            'status' => PixPayment::STATUS_REJECTED,
            'confirmed_at' => now(),
            'bank_state' => 'HTTP ' . $response->status(),
            'rejection_detail' => $detalhe,
            'response_payload' => $response->json(),
        ])->save();

        Log::channel('sicoob')->error('Sicoob: confirmação recusada', [
            'pix_payment_id' => $payment->id,
            'end_to_end_id' => $payment->end_to_end_id,
            'status' => $response->status(),
            'detalhe' => $detalhe,
            'violacoes' => $response->json('violacoes'),
        ]);

        // Saldo insuficiente tem tratamento próprio: a tela precisa dizer "põe
        // dinheiro na conta", não "o banco recusou".
        if ($this->pareceSaldoInsuficiente($detalhe)) {
            throw new SicoobInsufficientFundsException(
                'O Sicoob recusou o Pix por saldo insuficiente.',
                ['pix_payment_id' => $payment->id, 'detalhe' => $detalhe]
            );
        }

        throw new SicoobPaymentRejectedException(
            'O Sicoob recusou a confirmação do pagamento (HTTP ' . $response->status() . ').',
            $detalhe,
            [
                'pix_payment_id' => $payment->id,
                'end_to_end_id' => $payment->end_to_end_id,
                'violacoes' => $response->json('violacoes'),
            ]
        );
    }

    /* =====================================================================
     | Auxiliares
     |=====================================================================*/

    /**
     * @param  array<string, mixed>  $body
     *
     * @throws ConnectionException
     */
    protected function post(string $path, array $body): Response
    {
        return $this->auth
            ->client(config('sicoob.scopes.pix'), (bool) config('sicoob.pix.enviar_client_id', true))
            ->post($this->url($path), $body);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('sicoob.pix.base_url'), '/') . $path;
    }

    /**
     * Junta a mensagem de erro no formato RFC 7807 que o Sicoob usa
     * (`{type, title, status, detail, violacoes[]}`) numa frase legível.
     */
    protected function detalheDoErro(Response $response): ?string
    {
        $partes = array_filter([
            $response->json('detail'),
            $response->json('title'),
            // Formato da API Conta Corrente, que às vezes aparece no gateway.
            $response->json('mensagem'),
        ]);

        foreach ((array) $response->json('violacoes') as $violacao) {
            if (is_array($violacao) && isset($violacao['razao'])) {
                $partes[] = $violacao['razao'];
            }
        }

        $texto = trim(implode(' ', array_unique($partes)));

        return $texto === '' ? null : mb_substr($texto, 0, 500);
    }

    /**
     * Corpo da resposta de erro, encurtado, para o log e o diagnóstico.
     *
     * Erros de autenticação do gateway não vêm no formato RFC 7807 do Pix — são
     * `{httpCode, httpMessage, moreInformation}` — e é justamente o
     * `moreInformation` que diz se o problema foi credencial ou escopo. Nada
     * aqui contém segredo nosso: é o que o Sicoob respondeu.
     */
    protected function corpoDoErro(Response $response): string
    {
        $json = $response->json();

        if (is_array($json) && $json !== []) {
            return mb_substr((string) json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500);
        }

        return mb_substr(trim($response->body()), 0, 500) ?: '(corpo vazio)';
    }

    protected function pareceSaldoInsuficiente(?string $detalhe): bool
    {
        if ($detalhe === null) {
            return false;
        }

        $detalhe = mb_strtolower($detalhe);

        return str_contains($detalhe, 'saldo')
            && (str_contains($detalhe, 'insuficiente') || str_contains($detalhe, 'indisponível'));
    }

    /**
     * A chave Pix é um dado do freelancer (CPF, telefone, e-mail). No log de
     * erro basta o suficiente para identificar qual foi.
     */
    protected function mascararChave(string $chave): string
    {
        $chave = trim($chave);
        $tamanho = mb_strlen($chave);

        if ($tamanho <= 4) {
            return str_repeat('*', $tamanho);
        }

        return mb_substr($chave, 0, 2) . str_repeat('*', $tamanho - 4) . mb_substr($chave, -2);
    }

    protected function apenasDigitos(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }

    /** @return array<string, mixed> */
    protected function contextoDe(PixPayment $payment, Response $response): array
    {
        return [
            'pix_payment_id' => $payment->id,
            'end_to_end_id' => $payment->end_to_end_id,
            'status' => $response->status(),
        ];
    }
}
