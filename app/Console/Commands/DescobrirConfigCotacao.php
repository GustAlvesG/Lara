<?php

namespace App\Console\Commands;

use App\Services\Questor\QuestorCadastroRepository;
use App\Services\Questor\QuestorGate;
use Illuminate\Console\Command;
use Throwable;

/**
 * Descobre, contra a base real, os dois valores que o mapa de cotação NÃO pode
 * chutar — e imprime o que colocar no `.env`.
 *
 * NÃO GRAVA NADA. As duas consultas são as 0.1b e 0.2 do arquivo de queries, e
 * ambas são `SELECT` puros contra o Questor.
 *
 * POR QUE ISTO EXISTE, e não uma constante no código:
 *
 * 1. `TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS` não tem FK declarada (é
 *    DEFAULT 2). Não há nada no schema que diga qual código significa
 *    "cancelada" — e chutar errado faz o histórico de compras esconder notas
 *    boas em silêncio, que é o pior jeito de errar um preço.
 *
 * 2. `TBL_CME.X_ATUALIZA_DT_ULTIMA_COMPRA` é o flag que o próprio Questor usa
 *    para dizer "esta operação conta como compra". Antes de fechar o filtro do
 *    módulo nele, vale conferir que nesta base ele de fato separa compra de
 *    devolução, transferência e remessa.
 */
class DescobrirConfigCotacao extends Command
{
    protected $signature = 'cotacao:descobrir-config
                            {--cme=15 : Quantas operações (CME) listar}';

    protected $description = 'Roda as consultas de descoberta do mapa de cotação e imprime os valores para o .env. Não grava nada.';

    public function handle(QuestorCadastroRepository $cadastros): int
    {
        $this->info('Mapa de cotação — descoberta de configuração');
        $this->line('Somente leitura: nenhum registro do Questor é alterado.');
        $this->newLine();

        $ping = QuestorGate::ping();

        if (! $ping['ok']) {
            $this->error('Conexão com o Questor: FALHOU — ' . $ping['erro']);
            $this->line('Confira QUESTOR_ENABLED e as credenciais da conexão ' . config('questor.connection') . '.');

            return self::FAILURE;
        }

        $this->info('Conexão: ok (' . config('questor.database') . ')');
        $this->newLine();

        try {
            $sugestao = $this->status($cadastros);
            $this->newLine();
            $this->operacoes($cadastros);
        } catch (Throwable $e) {
            $this->error('Falha ao consultar o Questor: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->comment('--- Copie para o .env ------------------------------------------');
        $this->line('QUESTOR_STATUS_NF_CANCELADA=' . ($sugestao ?? ''));
        $this->comment('----------------------------------------------------------------');

        if ($sugestao === null) {
            $this->newLine();
            $this->warn('Nenhum status com cara de "cancelada" foi identificado automaticamente.');
            $this->line('Leia a tabela acima e escolha o código à mão. Deixar a chave VAZIA é');
            $this->line('uma resposta legítima: sem ela, nenhuma nota é descartada por status —');
            $this->line('preferível a chutar um número e sumir com compras boas do histórico.');
        }

        return self::SUCCESS;
    }

    /**
     * Query 0.1b — os status realmente presentes nas notas de entrada.
     *
     * @return int|null o código sugerido para "cancelada", se der para inferir
     */
    private function status(QuestorCadastroRepository $cadastros): ?int
    {
        $this->info('0.1b — CD_STATUS presentes em TBL_COMPRAS_NOTAFISCAL_ENTRADA');
        $this->line('(LEFT JOIN com TBL_STATUS: a coluna não tem FK, e status órfão precisa aparecer)');

        $linhas = $cadastros->statusDasNotasEntrada();

        if ($linhas->isEmpty()) {
            $this->warn('Nenhuma nota de entrada encontrada.');

            return null;
        }

        $this->table(
            ['CD_STATUS', 'DS_STATUS', 'Notas', 'Da data', 'Até'],
            $linhas->map(fn (object $l) => [
                $l->CD_STATUS,
                $l->DS_STATUS ?? '— sem cadastro em TBL_STATUS —',
                number_format((int) $l->QTD, 0, ',', '.'),
                $this->data($l->DT_MIN ?? null),
                $this->data($l->DT_MAX ?? null),
            ])->all()
        );

        // Inferência conservadora: só sugere quando a descrição diz
        // "cancel...". Qualquer coisa mais esperta aqui vira chute.
        $candidato = $linhas->first(fn (object $l) => str_contains(
            mb_strtoupper((string) ($l->DS_STATUS ?? '')),
            'CANCEL'
        ));

        if ($candidato !== null) {
            $this->info(sprintf(
                'Candidato a "cancelada": CD_STATUS %d (%s), em %s nota(s).',
                $candidato->CD_STATUS,
                $candidato->DS_STATUS,
                number_format((int) $candidato->QTD, 0, ',', '.')
            ));

            return (int) $candidato->CD_STATUS;
        }

        return null;
    }

    /**
     * Query 0.2 — as operações em uso e quais contam como compra.
     */
    private function operacoes(QuestorCadastroRepository $cadastros): void
    {
        $limite = max(1, (int) $this->option('cme'));

        $this->info('0.2 — CME em uso nos itens de entrada');
        $this->line('X_ATUALIZA_DT_ULTIMA_COMPRA = 1 é o que o módulo trata como COMPRA.');

        $linhas = $cadastros->operacoesEmUso();

        if ($linhas->isEmpty()) {
            $this->warn('Nenhuma operação encontrada nos itens de entrada.');

            return;
        }

        $this->table(
            ['CD_CME', 'DS_CME', 'CFOP', 'Estoque', 'Custo médio', 'Conta como compra', 'Itens'],
            $linhas->take($limite)->map(fn (object $l) => [
                $l->CD_CME,
                mb_strimwidth((string) $l->DS_CME, 0, 42, '…'),
                $l->CD_CFOP_CONTRIBUINTE,
                ((int) $l->X_ESTOQUE) ? 'sim' : 'não',
                ((int) $l->X_CUSTO_MEDIO) ? 'sim' : 'não',
                ((int) $l->X_ATUALIZA_DT_ULTIMA_COMPRA) ? 'SIM' : 'não',
                number_format((int) $l->QTD_ITENS, 0, ',', '.'),
            ])->all()
        );

        $compra = $linhas->where('X_ATUALIZA_DT_ULTIMA_COMPRA', 1)->count();

        $this->line(sprintf(
            '%d de %d operações em uso contam como compra.',
            $compra,
            $linhas->count()
        ));
        $this->line('Confira se as que ficaram de fora são mesmo devolução, transferência,');
        $this->line('remessa ou industrialização. Se alguma compra de verdade estiver fora,');
        $this->line('o cadastro do CME no Questor é que precisa ser corrigido — não o filtro.');
    }

    private function data(mixed $valor): string
    {
        return filled($valor) ? \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y') : '—';
    }
}
