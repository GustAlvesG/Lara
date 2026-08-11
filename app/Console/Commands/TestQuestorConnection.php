<?php

namespace App\Console\Commands;

use App\Services\Questor\QuestorAuthorizationWriter;
use App\Services\Questor\QuestorGate;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pré-checagem da integração com o Questor — conexão, escopo da fila, usuário
 * técnico e, se pedido, a simulação de uma autorização.
 *
 * NÃO GRAVA NADA NO ERP. Tudo o que ele faz é `SELECT`: a opção `--simular`
 * chama o mesmo serviço da tela, que nesta versão monta o `UPDATE` e conta as
 * linhas que ele pegaria, sem executá-lo.
 *
 * Existe pelo mesmo motivo do `sicoob:testar`: a conferência se repete a cada
 * troca de servidor, de senha ou de escopo, e fazê-la pelo Tinker não deixa
 * rastro do que foi verificado.
 */
class TestQuestorConnection extends Command
{
    protected $signature = 'questor:testar
                            {--ordens=5 : Quantas ordens pendentes listar}
                            {--simular= : CD_ORDEM_COMPRA para simular a autorização (não grava)}';

    protected $description = 'Verifica a conexão com o Questor, a fila de ordens pendentes e o usuário técnico. Não grava nada.';

    public function handle(QuestorPurchaseOrders $orders, QuestorAuthorizationWriter $writer): int
    {
        $this->info('Questor — pré-checagem da integração de ordem de compra');
        $this->line('Nenhum registro é alterado por este comando.');
        $this->newLine();

        $config = QuestorGate::summary();

        $this->table(['Configuração', 'Valor'], [
            ['Módulo ligado', $config['enabled'] ? 'sim' : 'NÃO (QUESTOR_ENABLED)'],
            ['Modo', $config['dry_run'] ? 'simulação (nada é gravado)' : 'gravação SOLICITADA — ainda não liberada'],
            ['Conexão', $config['connection']],
            ['Banco', $config['database']],
            ['Usuário técnico', $config['usuario_tecnico'] ?? '— não configurado —'],
            ['Filiais', $config['filiais'] ? implode(', ', $config['filiais']) : 'todas'],
            ['Corte da fila', $config['desde'] ?: 'todo o histórico'],
        ]);

        $ping = QuestorGate::ping();

        if (!$ping['ok']) {
            $this->error('Conexão: FALHOU — ' . $ping['erro']);

            return self::FAILURE;
        }

        $this->info('Conexão: ok');
        $this->newLine();

        try {
            $this->conferirUsuarioTecnico($orders);
            $this->listarOrdens($orders);

            if ($this->option('simular')) {
                $this->simular($writer, (int) $this->option('simular'));
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function conferirUsuarioTecnico(QuestorPurchaseOrders $orders): void
    {
        $codigo = config('questor.usuario_tecnico');

        if ($codigo === null) {
            $this->warn('Usuário técnico não configurado (QUESTOR_USUARIO_TECNICO). Necessário antes de liberar a gravação.');
            $this->newLine();

            return;
        }

        $usuario = $orders->technicalUser();

        if ($usuario === null) {
            $this->error("Usuário técnico {$codigo} não existe em TBL_USUARIOS.");
            $this->newLine();

            return;
        }

        $this->table(['Usuário técnico', 'Valor'], [
            ['CD_CODUSUARIO', $usuario->CD_CODUSUARIO],
            ['DS_LOGIN', $usuario->DS_LOGIN],
            ['DS_USUARIO', $usuario->DS_USUARIO],
            ['X_ATIVO', (int) $usuario->X_ATIVO ? 'sim' : 'NÃO'],
            ['X_AUTORIZA_ORDEM_COMPRA', (int) $usuario->X_AUTORIZA_ORDEM_COMPRA ? 'sim' : 'NÃO'],
            ['X_REPROVA_ORDEM_COMPRA', (int) $usuario->X_REPROVA_ORDEM_COMPRA ? 'sim' : 'NÃO'],
        ]);
    }

    private function listarOrdens(QuestorPurchaseOrders $orders): void
    {
        $resumo = $orders->pendingSummary();

        $this->line(sprintf(
            'Fila: %d ordens pendentes, R$ %s parados.',
            $resumo['quantidade'],
            number_format($resumo['valor'], 2, ',', '.')
        ));

        $quantas = max(1, (int) $this->option('ordens'));

        $linhas = $orders->pending()->take($quantas)->map(fn($o) => [
            $o->CD_ORDEM_COMPRA,
            $o->CD_FILIAL,
            mb_strimwidth((string) ($o->FORNECEDOR_FANTASIA ?: $o->FORNECEDOR_RAZAO_SOCIAL), 0, 30, '…'),
            $o->NR_ITENS,
            number_format((float) $o->VL_TOTAL, 2, ',', '.'),
            $o->DT_CADASTRO,
        ])->all();

        if ($linhas === []) {
            $this->warn('Nenhuma ordem pendente no escopo configurado.');

            return;
        }

        $this->newLine();
        $this->table(['Ordem', 'Filial', 'Fornecedor', 'Itens', 'Valor', 'Cadastro'], $linhas);
    }

    private function simular(QuestorAuthorizationWriter $writer, int $ordem): void
    {
        $this->newLine();
        $this->info("Simulando a autorização da ordem {$ordem} — nada será gravado.");

        $previa = $writer->approve($ordem);

        $this->newLine();
        $this->line('SQL que seria enviado:');
        $this->line($previa['sql']);
        $this->newLine();
        $this->line('Parâmetros: ' . json_encode($previa['bindings'], JSON_UNESCAPED_UNICODE));
        $this->line('Linhas que seriam afetadas: ' . $previa['linhas_afetadas']);

        if ($previa['impedimentos'] !== []) {
            $this->newLine();
            $this->warn('A gravação real esbarraria em:');
            foreach ($previa['impedimentos'] as $impedimento) {
                $this->warn(' - ' . $impedimento);
            }

            return;
        }

        $this->newLine();
        $this->info('Nenhum impedimento: a instrução acima gravaria corretamente quando a escrita for liberada.');
    }
}
