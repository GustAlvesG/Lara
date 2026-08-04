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
                            {--chave= : Chave Pix para consultar no DICT (opcional, não move dinheiro)}';

    protected $description = 'Verifica certificado, token, saldo e consulta de chave no DICT do Sicoob. Não transfere dinheiro.';

    public function handle(
        SicoobAuthService $auth,
        SicoobContaCorrenteService $contaCorrente,
        SicoobPixPagamentoService $pix,
    ): int {
        $this->info('Sicoob — pré-checagem da integração');
        $this->line('Nenhum pagamento é efetivado por este comando.');
        $this->newLine();

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
        $this->line('<info>OK</info>  Token obtido (' . strlen($token) . ' caracteres), escopos: '
            . implode(' ', config('sicoob.scopes.pix')));

        return true;
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

    private function mascarar(string $valor): string
    {
        $tamanho = strlen($valor);

        if ($tamanho <= 8) {
            return str_repeat('*', $tamanho);
        }

        return substr($valor, 0, 4) . str_repeat('*', $tamanho - 8) . substr($valor, -4);
    }
}
