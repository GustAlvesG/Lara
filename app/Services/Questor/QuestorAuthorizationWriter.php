<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O carimbo de autorização no Questor — que nesta versão **só é simulado**.
 *
 * A gravação real é um `UPDATE` em `TBL_COMPRAS_ORDEM_COMPRA`, e ela ainda não
 * está liberada: enquanto `questor.dry_run` estiver ligado (o padrão), este
 * serviço monta a instrução exata, lê o estado atual da ordem, conta quantas
 * linhas o `WHERE` casaria hoje e devolve o antes/depois — sem executar nada.
 * Com o dry run desligado, ele recusa a operação com a lista do que falta para
 * destravar (ver {@see self::assertWritesReleased()}); é assim que o módulo
 * sobe para produção sem poder mexer no ERP no mesmo deploy.
 *
 * A contagem de linhas é a parte que dá valor à simulação: ela usa exatamente o
 * mesmo predicado do UPDATE, então responde a pergunta que importa — "se eu
 * mandasse agora, pegaria a ordem certa, ou zero linhas porque alguém já
 * autorizou pela tela nativa?".
 *
 * Duas assimetrias entre aprovar e reprovar, ambas confirmadas na base:
 *  - aprovar NÃO muda `CD_STATUS` (a ordem segue PENDENTE, só ganha o carimbo);
 *  - reprovar move para REPROVADO e guarda o status anterior e o motivo.
 *
 * Nada aqui toca `VL_FRETE`, `VL_FRETEAP` ou `VL_OUTRAS` — os únicos campos com
 * trigger de recálculo de custo na tabela.
 */
class QuestorAuthorizationWriter
{
    public const ACTION_APPROVE = 'aprovacao';
    public const ACTION_REJECT = 'reprovacao';

    public function __construct(private readonly QuestorPurchaseOrders $orders)
    {
    }

    /**
     * Simula a autorização final de uma ordem.
     *
     * @return array<string, mixed> prévia no formato descrito em {@see self::preview()}
     *
     * @throws QuestorException
     */
    public function approve(int $cdOrdemCompra): array
    {
        $this->assertWritesReleased();

        $ordem = $this->orders->find($cdOrdemCompra);

        $sql = sprintf(
            'UPDATE %s
                SET CD_USUARIO_AUTORIZOU = ?,
                    DT_AUTORIZACAO = GETDATE()
              WHERE CD_ORDEM_COMPRA = ?
                AND CD_STATUS = ?
                AND CD_USUARIO_AUTORIZOU IS NULL',
            $this->table()
        );

        $bindings = [
            $this->technicalUserCode(),
            $cdOrdemCompra,
            $this->pendingStatus(),
        ];

        return $this->preview(self::ACTION_APPROVE, $ordem, $sql, $bindings, [
            'CD_USUARIO_AUTORIZOU' => $this->technicalUserCode(),
            'DT_AUTORIZACAO' => 'GETDATE() — relógio do servidor do Questor',
            'CD_STATUS' => (int) $ordem->CD_STATUS . ' (inalterado: autorizar não muda o status)',
        ], [
            'CD_ORDEM_COMPRA = ?' => $cdOrdemCompra,
            'CD_STATUS = ?' => $this->pendingStatus(),
            'CD_USUARIO_AUTORIZOU IS NULL' => null,
        ]);
    }

    /**
     * Simula a reprovação final de uma ordem.
     *
     * O motivo é truncado em `questor.motivo_max` porque `DS_MOTIVO_REPROVADO`
     * é varchar(100) — sem isso o SQL Server recusaria a linha inteira. O texto
     * completo, quando houver, é responsabilidade do histórico da Lara.
     *
     * @return array<string, mixed>
     *
     * @throws QuestorException
     */
    public function reject(int $cdOrdemCompra, string $motivo): array
    {
        $this->assertWritesReleased();

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new QuestorException('Informe o motivo da reprovação.');
        }

        $motivo = Str::limit($motivo, (int) config('questor.motivo_max', 100), '');

        $ordem = $this->orders->find($cdOrdemCompra);

        $sql = sprintf(
            'UPDATE %s
                SET CD_STATUS = ?,
                    CD_STATUS_ANTERIOR = ?,
                    CD_USUARIO_REPROVOU = ?,
                    DT_REPROVACAO = GETDATE(),
                    DS_MOTIVO_REPROVADO = ?
              WHERE CD_ORDEM_COMPRA = ?
                AND CD_STATUS = ?
                AND CD_USUARIO_REPROVOU IS NULL',
            $this->table()
        );

        $bindings = [
            $this->rejectedStatus(),
            $this->pendingStatus(),
            $this->technicalUserCode(),
            $motivo,
            $cdOrdemCompra,
            $this->pendingStatus(),
        ];

        return $this->preview(self::ACTION_REJECT, $ordem, $sql, $bindings, [
            'CD_STATUS' => $this->rejectedStatus() . ' (REPROVADO)',
            'CD_STATUS_ANTERIOR' => $this->pendingStatus(),
            'CD_USUARIO_REPROVOU' => $this->technicalUserCode(),
            'DT_REPROVACAO' => 'GETDATE() — relógio do servidor do Questor',
            'DS_MOTIVO_REPROVADO' => $motivo,
        ], [
            'CD_ORDEM_COMPRA = ?' => $cdOrdemCompra,
            'CD_STATUS = ?' => $this->pendingStatus(),
            'CD_USUARIO_REPROVOU IS NULL' => null,
        ], [
            // O caminho de reprovação ainda não foi observado ao vivo — só há o
            // padrão de 28 casos históricos. Enquanto o teste da seção 6.2 da
            // especificação não for feito, quem lê a simulação precisa saber.
            'A reprovação foi montada a partir do padrão histórico da base; o teste ao vivo '
            . '(seção 6.2 da especificação) ainda não foi realizado.',
        ]);
    }

    /**
     * Monta a prévia: o que existe hoje, o que a instrução mudaria, quantas
     * linhas ela pegaria e o que ainda impede a gravação real.
     *
     * @param  array<int, mixed>  $bindings
     * @param  array<string, mixed>  $depois  campos e valores que o UPDATE gravaria
     * @param  array<string, mixed>  $predicado  condições do WHERE, para a contagem
     * @param  array<int, string>  $ressalvas
     * @return array{acao: string, cd_ordem_compra: int, executado: bool, dry_run: bool,
     *               sql: string, bindings: array<int, mixed>, antes: array<string, mixed>,
     *               depois: array<string, mixed>, linhas_afetadas: int,
     *               impedimentos: array<int, string>, ressalvas: array<int, string>,
     *               usuario_tecnico: object|null}
     */
    private function preview(
        string $acao,
        object $ordem,
        string $sql,
        array $bindings,
        array $depois,
        array $predicado,
        array $ressalvas = [],
    ): array {
        $linhas = $this->matchingRows($predicado);
        // Lido uma vez: `blockers()` e a prévia querem o mesmo usuário, e ele
        // custa uma ida ao Questor.
        $tecnico = $this->orders->technicalUser();

        $previa = [
            'acao' => $acao,
            'cd_ordem_compra' => (int) $ordem->CD_ORDEM_COMPRA,
            // Enquanto for `false`, nada foi ao ERP. É o campo que a tela lê
            // para dizer "simulação" em vez de "gravado".
            'executado' => false,
            'dry_run' => QuestorGate::dryRun(),
            'sql' => $sql,
            'bindings' => $bindings,
            'antes' => [
                'CD_STATUS' => $ordem->CD_STATUS,
                'DS_STATUS' => $ordem->DS_STATUS ?? null,
                'CD_USUARIO_AUTORIZOU' => $ordem->CD_USUARIO_AUTORIZOU,
                'DT_AUTORIZACAO' => $ordem->DT_AUTORIZACAO,
                'CD_USUARIO_REPROVOU' => $ordem->CD_USUARIO_REPROVOU,
                'DT_REPROVACAO' => $ordem->DT_REPROVACAO,
                'DS_MOTIVO_REPROVADO' => $ordem->DS_MOTIVO_REPROVADO,
            ],
            'depois' => $depois,
            'linhas_afetadas' => $linhas,
            'impedimentos' => $this->blockers($ordem, $acao, $linhas, $tecnico),
            'ressalvas' => $ressalvas,
            'usuario_tecnico' => $tecnico,
        ];

        Log::info('Questor: simulação de ' . $acao, [
            'cd_ordem_compra' => $previa['cd_ordem_compra'],
            'linhas_afetadas' => $linhas,
            'impedimentos' => $previa['impedimentos'],
            'user_id' => auth()->id(),
        ]);

        return $previa;
    }

    /**
     * Quantas linhas o UPDATE pegaria se fosse enviado agora — mesmo predicado,
     * `SELECT COUNT(*)` no lugar do `SET`.
     *
     * Zero aqui não é erro de conexão: quase sempre significa que a ordem já
     * saiu da fila (foi autorizada na tela nativa, faturada ou cancelada) entre
     * a abertura da tela e o clique.
     *
     * @param  array<string, mixed>  $predicado
     */
    private function matchingRows(array $predicado): int
    {
        $where = [];
        $bindings = [];

        foreach ($predicado as $condicao => $valor) {
            $where[] = $condicao;

            if ($valor !== null) {
                $bindings[] = $valor;
            }
        }

        $sql = sprintf(
            'SELECT COUNT(*) AS TOTAL FROM %s WHERE %s',
            $this->table(),
            implode(' AND ', $where)
        );

        try {
            $linha = DB::connection(config('questor.connection'))->select($sql, $bindings)[0] ?? null;
        } catch (\Throwable $e) {
            report($e);

            throw new QuestorException(
                'Não foi possível conferir a ordem no Questor antes de simular a gravação.'
            );
        }

        return (int) ($linha->TOTAL ?? 0);
    }

    /**
     * O que impediria a gravação real de acontecer — a lista que precisa estar
     * vazia antes de se cogitar desligar o dry run.
     *
     * São impedimentos, não exceções: a simulação continua útil (e a tela
     * continua mostrando o SQL) mesmo com o usuário técnico ainda por criar.
     *
     * @return array<int, string>
     */
    private function blockers(object $ordem, string $acao, int $linhas, ?object $usuario): array
    {
        $impedimentos = [];

        $codigo = config('questor.usuario_tecnico');

        if ($codigo === null) {
            $impedimentos[] = 'Usuário técnico não configurado (QUESTOR_USUARIO_TECNICO).';
        } elseif ($usuario === null) {
            $impedimentos[] = "O usuário técnico {$codigo} não existe em TBL_USUARIOS.";
        } else {
            if (!(int) $usuario->X_ATIVO) {
                $impedimentos[] = "O usuário técnico {$codigo} está inativo no Questor.";
            }

            // Autorizar e reprovar são permissões separadas no Questor: um
            // usuário técnico pode ter uma e não a outra.
            $permissao = $acao === self::ACTION_APPROVE
                ? ['X_AUTORIZA_ORDEM_COMPRA', 'autorizar']
                : ['X_REPROVA_ORDEM_COMPRA', 'reprovar'];

            if (!(int) ($usuario->{$permissao[0]} ?? 0)) {
                $impedimentos[] = "O usuário técnico {$codigo} não tem permissão de {$permissao[1]} ordem de compra ({$permissao[0]} = 0).";
            }
        }

        if ($linhas === 0) {
            $impedimentos[] = 'Nenhuma linha seria afetada: a ordem já saiu da fila (autorizada, reprovada ou com o status alterado no Questor).';
        }

        if ($acao === self::ACTION_APPROVE && $ordem->CD_USUARIO_AUTORIZOU !== null) {
            $impedimentos[] = 'Esta ordem já tem autorizador gravado no Questor.';
        }

        if ($acao === self::ACTION_REJECT && $ordem->CD_USUARIO_REPROVOU !== null) {
            $impedimentos[] = 'Esta ordem já tem reprovador gravado no Questor.';
        }

        return $impedimentos;
    }

    /**
     * A trava de escrita.
     *
     * Esta versão do módulo é de leitura e simulação: desligar `dry_run` sozinho
     * não libera a gravação, e é isso que esta exceção diz. Quando o `UPDATE`
     * real for implementado, é aqui que ele passa a ser permitido — depois de
     * cumpridos os itens listados na mensagem.
     *
     * @throws QuestorException
     */
    private function assertWritesReleased(): void
    {
        QuestorGate::ensureEnabled();

        if (!QuestorGate::dryRun()) {
            throw new QuestorException(
                'A gravação no Questor ainda não foi liberada nesta versão do módulo — ela só simula. '
                . 'Mantenha QUESTOR_DRY_RUN=true. Para destravar a escrita é preciso, antes: criar o usuário '
                . 'técnico no Questor, rodar o teste ao vivo de reprovação (seção 6.2 da especificação) e '
                . 'conceder UPDATE em TBL_COMPRAS_ORDEM_COMPRA ao login da Lara.'
            );
        }
    }

    private function technicalUserCode(): ?int
    {
        $codigo = config('questor.usuario_tecnico');

        return $codigo === null ? null : (int) $codigo;
    }

    private function pendingStatus(): int
    {
        return (int) config('questor.status.pendente', 1);
    }

    private function rejectedStatus(): int
    {
        return (int) config('questor.status.reprovado', 5);
    }

    private function table(): string
    {
        return sprintf(
            '%s.%s.TBL_COMPRAS_ORDEM_COMPRA',
            config('questor.database', 'FUNCSIDERURG'),
            config('questor.schema', 'dbo')
        );
    }
}
