<?php

namespace App\Console\Commands;

use App\Services\Sicoob\SicoobAuthService;
use App\Services\Sicoob\SicoobContaCorrenteService;
use App\Services\Sicoob\SicoobPixPagamentoService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pré-checagem da integração Sicoob — certificado, token, saldo e DICT.
 *
 * NÃO TRANSFERE DINHEIRO. As três chamadas que ele faz são de leitura:
 * obter token, consultar saldo e a iniciação DICT (`POST /pagamentos`), que
 * apenas consulta a chave e reserva um identificador. A efetivação
 * (`/pagamentos/confirmacao`), que é a que move dinheiro, não é chamada aqui em
 * nenhuma hipótese.
 *
 * Existe porque essa conferência se repete: a cada renovação de certificado, a
 * cada troca de servidor e na virada de sandbox para produção. Fazê-la pelo
 * Tinker exige contornar o psysh (o usuário `www-data` não tem HOME gravável) e
 * não deixa rastro do que foi verificado.
 */
class TestSicoobConnection extends Command
{
    protected $signature = 'sicoob:testar
                            {--chave= : Chave Pix para consultar no DICT (opcional, não move dinheiro)}
                            {--sem-client-id : Testa o Pix SEM o header client_id (a spec do Pix Pagamentos não o declara)}
                            {--headers : Mostra os headers que o app envia no fio e varre variações deles}';

    protected $description = 'Verifica certificado, token, saldo e consulta de chave no DICT do Sicoob. Não transfere dinheiro.';

    public function handle(
        SicoobAuthService $auth,
        SicoobContaCorrenteService $contaCorrente,
        SicoobPixPagamentoService $pix,
    ): int {
        $this->info('Sicoob — pré-checagem da integração');
        $this->line('Nenhum pagamento é efetivado por este comando.');
        $this->newLine();

        if ($this->option('sem-client-id')) {
            config(['sicoob.pix.enviar_client_id' => false]);
            $this->comment('Modo de teste: header client_id OMITIDO nas chamadas de Pix.');
            $this->newLine();
        }

        $ok = $this->mostrarConfiguracao()
            && $this->conferirCertificados();

        if (!$ok) {
            return self::FAILURE;
        }

        $ok = $this->testarToken($auth);

        if ($ok) {
            // Saldo não é bloqueante: falhar aqui não impede pagamento, então
            // também não deve reprovar a checagem inteira.
            $this->testarSaldo($contaCorrente);

            if ($this->option('headers')) {
                $this->diagnosticarCabecalhos($auth, (string) $this->option('chave'));
            }

            if ($this->option('chave')) {
                $ok = $this->testarDict($pix, (string) $this->option('chave'));
            } else {
                $this->newLine();
                $this->comment('Chave não informada — pule com --chave=sua-chave-pix para conferir o DICT.');
            }
        }

        $this->newLine();

        if (!$ok) {
            $this->error('Pré-checagem REPROVADA. Não ligue o SICOOB_PIX_ENABLED até resolver.');

            return self::FAILURE;
        }

        $this->info('Pré-checagem OK.');

        if (!config('sicoob.enabled')) {
            $this->comment('SICOOB_PIX_ENABLED continua false — o "Dar baixa" ainda é marcação manual.');
        }

        return self::SUCCESS;
    }

    private function mostrarConfiguracao(): bool
    {
        $ambiente = config('sicoob.environment');
        $baseUrl = (string) config('sicoob.pix.base_url');

        $this->table(['Configuração', 'Valor'], [
            ['Ambiente', $ambiente],
            ['Base URL (Pix)', $baseUrl],
            ['Base URL (Conta Corrente)', config('sicoob.conta_corrente.base_url')],
            ['Pix habilitado', var_export((bool) config('sicoob.enabled'), true)],
            ['Teto por transferência', 'R$ ' . number_format((float) config('sicoob.pix.max_amount'), 2, ',', '.')],
            ['Valida titular da chave', var_export((bool) config('sicoob.pix.validar_titular'), true)],
            ['client_id', $this->mascarar((string) config('sicoob.client_id'))],
            ['Header client_id no Pix', var_export((bool) config('sicoob.pix.enviar_client_id', true), true)],
            ['Conta corrente', config('sicoob.conta_corrente.numero_conta') ?: '(não configurada)'],
        ]);

        if (blank(config('sicoob.client_id'))) {
            $this->error('SICOOB_CLIENT_ID está vazio.');

            return false;
        }

        // O descompasso entre o rótulo e a URL é o erro que faz alguém achar
        // que está testando quando já está mexendo em dinheiro de verdade.
        $urlEhProducao = str_contains($baseUrl, 'api.sicoob.com.br');

        if ($ambiente === 'producao' && !$urlEhProducao) {
            $this->warn('Ambiente marcado como "producao", mas a URL não é a de produção. Confira SICOOB_PIX_BASE_URL.');
        }

        if ($ambiente !== 'producao' && $urlEhProducao) {
            $this->warn('ATENÇÃO: a URL é a de PRODUÇÃO, mas o ambiente está marcado como "' . $ambiente . '".');
        }

        return true;
    }

    private function conferirCertificados(): bool
    {
        $cert = (string) config('sicoob.certificate.path');
        $key = (string) config('sicoob.certificate.key_path');
        $ok = true;

        foreach (['Certificado' => $cert, 'Chave privada' => $key] as $rotulo => $caminho) {
            if ($caminho === '') {
                $this->error("{$rotulo}: caminho não configurado.");
                $ok = false;

                continue;
            }

            if (!is_readable($caminho)) {
                $this->error("{$rotulo}: não encontrado ou sem permissão de leitura — {$caminho}");
                $this->line('  Rode como o usuário do PHP (www-data) e confira dono e permissão do arquivo.');
                $ok = false;

                continue;
            }

            $this->line("<info>OK</info>  {$rotulo}: {$caminho}");
        }

        return $ok;
    }

    private function testarToken(SicoobAuthService $auth): bool
    {
        try {
            $token = $auth->token(config('sicoob.scopes.pix'));
        } catch (Throwable $e) {
            $this->error('Token: FALHOU — ' . $e->getMessage());
            $this->line('  Veja storage/logs/sicoob-*.log para o detalhe.');

            return false;
        }

        // Só o tamanho: o token é credencial e não vai para a tela nem para o log.
        $this->line('<info>OK</info>  Token obtido (' . strlen($token) . ' caracteres)');

        return $this->conferirEscopos($token);
    }

    /**
     * Compara os escopos PEDIDOS com os efetivamente CONCEDIDOS no token.
     *
     * O SSO do Sicoob emite o token mesmo quando o app não tem algum dos
     * escopos pedidos — ele simplesmente devolve o subconjunto que o app possui,
     * sem erro. O resultado é um 401 depois, na API, sem nenhuma pista de que a
     * causa foi um escopo ausente lá atrás. Abrir o token aqui transforma esse
     * 401 misterioso numa resposta objetiva.
     */
    private function conferirEscopos(string $token): bool
    {
        $pedidos = (array) config('sicoob.scopes.pix');
        $concedidos = $this->escoposDoToken($token);

        if ($concedidos === null) {
            $this->comment('  Não foi possível ler os escopos do token (formato inesperado).');
            $this->line('  Escopos pedidos: ' . implode(' ', $pedidos));

            return true;
        }

        $faltando = array_diff($pedidos, $concedidos);

        if ($faltando === []) {
            $this->line('<info>OK</info>  Escopos concedidos: ' . implode(' ', $pedidos));

            return true;
        }

        $this->error('Escopos NÃO concedidos pelo Sicoob: ' . implode(', ', $faltando));
        $this->line('  Pedidos:    ' . implode(' ', $pedidos));
        $this->line('  Concedidos: ' . (implode(' ', $concedidos) ?: '(nenhum)'));
        $this->newLine();
        $this->line('  O token foi emitido mesmo assim — por isso a falha só aparece como 401 na API.');
        $this->line('  Resolve-se no portal/cooperativa, não no código: o produto precisa estar');
        $this->line('  contratado e habilitado para PRODUÇÃO no app.');

        return false;
    }

    /**
     * Escopos dentro do access token (claim `scope`).
     *
     * Decodifica o payload do JWT sem validar assinatura — aqui não se está
     * confiando no token, apenas lendo o que o próprio emissor declarou. Nada
     * do conteúdo é impresso além da lista de escopos.
     *
     * @return array<int, string>|null  null quando o token não é um JWT legível
     */
    private function escoposDoToken(string $token): ?array
    {
        $partes = explode('.', $token);

        if (count($partes) < 2) {
            return null;
        }

        $payload = base64_decode(strtr($partes[1], '-_', '+/'), false);

        if ($payload === false) {
            return null;
        }

        $dados = json_decode($payload, true);

        if (!is_array($dados) || !isset($dados['scope'])) {
            return null;
        }

        return array_values(array_filter(explode(' ', (string) $dados['scope'])));
    }

    private function testarSaldo(SicoobContaCorrenteService $contaCorrente): void
    {
        $saldo = $contaCorrente->saldoDisponivel();

        if ($saldo === null) {
            $this->warn('Saldo: não foi possível consultar.');
            $this->line('  Isso NÃO impede pagamentos — mas desliga a pré-checagem de saldo.');
            $this->line('  Confira SICOOB_CC_NUMERO_CONTA (só dígitos) e o log.');

            return;
        }

        $this->line('<info>OK</info>  Saldo disponível: R$ ' . number_format($saldo, 2, ',', '.'));

        $teto = (float) config('sicoob.pix.max_amount');

        if ($teto > 0 && $saldo < $teto) {
            $this->comment('  Saldo abaixo do teto por transferência — pagamentos maiores que o saldo serão barrados.');
        }
    }

    private function testarDict(SicoobPixPagamentoService $pix, string $chave): bool
    {
        try {
            $retorno = $pix->iniciar($chave);
        } catch (Throwable $e) {
            $this->error('DICT: FALHOU — ' . $e->getMessage());

            // O contexto carrega a resposta crua do Sicoob, que é onde está o
            // motivo real de um 401 — a mensagem da exceção só diz o código.
            if ($e instanceof \App\Exceptions\Sicoob\SicoobException) {
                foreach ($e->context() as $chaveCtx => $valor) {
                    $this->line('  ' . $chaveCtx . ': ' . (is_scalar($valor) ? $valor : json_encode($valor, JSON_UNESCAPED_UNICODE)));
                }
            }

            return false;
        }

        $proprietario = $retorno['proprietario'] ?? [];

        $this->newLine();
        $this->table(['Retorno do DICT', 'Valor'], [
            ['endToEndId', $retorno['endToEndId'] ?? '—'],
            ['Tipo da chave', $retorno['tipo'] ?? '—'],
            ['Titular', $proprietario['nome'] ?? '—'],
            ['Documento', $proprietario['identificador'] ?? '—'],
        ]);

        // O sandbox é um mock estático: devolve "string" nos campos do titular,
        // independentemente da chave enviada. Reconhecer isso evita alguém
        // concluir que a integração está pronta quando não consultou nada.
        if (($proprietario['nome'] ?? null) === 'string' || ($proprietario['identificador'] ?? null) === 'string') {
            $this->warn('Resposta do SANDBOX (mock estático) — nenhuma consulta real ao DICT foi feita.');
            $this->line('  Para valer, aponte SICOOB_PIX_BASE_URL para https://api.sicoob.com.br/pix-pagamentos/v2');

            return true;
        }

        $this->line('<info>OK</info>  Consulta real ao DICT. Confira se o titular acima é quem você espera.');

        return true;
    }

    /* ---------------------------------------------------------------------
     | Cabeçalhos
     |
     | A documentação do Sicoob atrela o 401 aos headers de autenticação, e a
     | única forma honesta de conferir isso é ler o que sai NO FIO — não o que
     | achamos que o código monta. `beforeSending` entrega a requisição PSR-7
     | já pronta, com tudo que o Guzzle acrescentou por conta própria.
     |
     | Depois da leitura, varre variações do header: presença, ausência e
     | grafia. Se alguma passar, ela é a resposta; se nenhuma mudar nada, o
     | problema não é cabeçalho — e isso também é uma conclusão útil.
     |-------------------------------------------------------------------*/

    private function diagnosticarCabecalhos(SicoobAuthService $auth, string $chave): void
    {
        $this->newLine();
        $this->info('Headers enviados pela aplicação (leitura do fio)');

        $url = rtrim((string) config('sicoob.pix.base_url'), '/') . '/pagamentos';
        $corpo = ['chave' => $chave !== '' ? $chave : 'diagnostico@invalido'];

        $enviados = [];

        try {
            $auth->client(config('sicoob.scopes.pix'), (bool) config('sicoob.pix.enviar_client_id', true))
                ->beforeSending(function ($request) use (&$enviados) {
                    $enviados = $request->headers();
                })
                ->post($url, $corpo);
        } catch (Throwable $e) {
            // A resposta não importa aqui — o objetivo é capturar os headers, e
            // `beforeSending` já rodou antes de qualquer falha de rede.
            $this->line('  (a chamada falhou, mas os headers abaixo foram os enviados)');
        }

        if ($enviados === []) {
            $this->warn('  Não foi possível capturar os headers.');

            return;
        }

        $linhas = [];

        foreach ($enviados as $nome => $valores) {
            $valor = implode(', ', (array) $valores);

            // Nem token nem client_id aparecem inteiros: este comando roda em
            // produção e a saída costuma virar anexo de chamado.
            if (strcasecmp($nome, 'Authorization') === 0) {
                $valor = 'Bearer <token de ' . max(0, strlen($valor) - 7) . ' caracteres>';
            } elseif (strcasecmp($nome, 'client_id') === 0) {
                $valor = $this->mascarar($valor);
            }

            $linhas[] = [$nome, $valor];
        }

        $this->table(['Header', 'Valor'], $linhas);

        $nomes = array_map('strtolower', array_keys($enviados));

        in_array('authorization', $nomes, true)
            ? $this->line('<info>OK</info>  Authorization presente')
            : $this->error('Authorization AUSENTE');

        if (config('sicoob.pix.enviar_client_id', true)) {
            in_array('client_id', $nomes, true)
                ? $this->line('<info>OK</info>  client_id presente')
                : $this->error('client_id deveria estar presente e não está');
        }

        $this->varrerVariacoesDeHeader($auth, $url, $corpo);
    }

    /**
     * Testa o mesmo POST com combinações diferentes de header, comparando os
     * códigos HTTP.
     *
     * Só a iniciação DICT é usada — ela consulta a chave e reserva um
     * identificador, sem mover dinheiro. Um 404 no lugar de 401 seria a
     * descoberta mais valiosa: significa que a autorização passou naquela
     * variação e apenas o recurso não existe.
     *
     * @param  array<string, mixed>  $corpo
     */
    private function varrerVariacoesDeHeader(SicoobAuthService $auth, string $url, array $corpo): void
    {
        $this->newLine();
        $this->info('Variações de cabeçalho (POST /pagamentos — não move dinheiro)');

        $clientId = (string) config('sicoob.client_id');

        $variacoes = [
            'Authorization + client_id' => ['client_id' => $clientId],
            'só Authorization' => [],
            'Client-Id (hífen, capitalizado)' => ['Client-Id' => $clientId],
            'client-id (hífen, minúsculo)' => ['client-id' => $clientId],
            'X-Client-Id' => ['X-Client-Id' => $clientId],
            'client_id + Accept: application/json' => ['client_id' => $clientId, 'Accept' => 'application/json'],
        ];

        $linhas = [];

        foreach ($variacoes as $rotulo => $headers) {
            // `comClientId: false` para que o header venha só do array acima —
            // senão a variação "só Authorization" viria com ele de qualquer forma.
            try {
                $resposta = $auth->client(config('sicoob.scopes.pix'), false)
                    ->withHeaders($headers)
                    ->post($url, $corpo);

                $codigo = (string) $resposta->status();
                $detalhe = mb_substr((string) ($resposta->json('moreInformation')
                    ?? $resposta->json('detail')
                    ?? $resposta->json('title')
                    ?? ''), 0, 60);
            } catch (Throwable $e) {
                $codigo = 'erro';
                $detalhe = mb_substr($e->getMessage(), 0, 60);
            }

            $linhas[] = [$rotulo, $codigo, $detalhe];
        }

        $this->table(['Variação', 'HTTP', 'Mensagem'], $linhas);

        $codigos = array_column($linhas, 1);

        if (in_array('200', $codigos, true) || in_array('201', $codigos, true)) {
            $this->line('<info>OK</info>  Alguma variação passou — veja qual na tabela e ajuste a configuração.');
        } elseif (in_array('404', $codigos, true)) {
            $this->comment('  Houve 404: naquela variação a autorização PASSOU (só o recurso não existe).');
        } elseif (count(array_unique($codigos)) === 1) {
            $this->comment('  Todas as variações deram o mesmo código — o problema NÃO é cabeçalho.');
            $this->line('  Sobra a autorização do produto Pix Pagamentos para este client_id no gateway.');
        }
    }

    private function mascarar(string $valor): string
    {
        $tamanho = strlen($valor);

        if ($tamanho <= 8) {
            return str_repeat('*', $tamanho);
        }

        return substr($valor, 0, 4) . str_repeat('*', $tamanho - 8) . substr($valor, -4);
    }
}
