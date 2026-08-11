<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use App\Models\QuestorOrderDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * O carimbo de autorização no Questor.
 *
 * Dois modos, decididos por `questor.dry_run`:
 *
 *  - **simulação** (padrão): monta o `UPDATE`, lê o estado atual da ordem, conta
 *    quantas linhas o `WHERE` casaria hoje e devolve o antes/depois. Nada é
 *    escrito.
 *  - **gravação**: executa o `UPDATE` no ERP de produção, relê a ordem para
 *    conferir o carimbo e registra a decisão em `questor_order_decisions`.
 *
 * A contagem de linhas é o que dá valor aos dois modos: ela usa exatamente o
 * mesmo predicado do `UPDATE`, então responde a pergunta que importa — pegaria a
 * ordem certa, ou zero linhas porque alguém já decidiu pela tela nativa entre a
 * abertura da tela e o clique? Uma gravação que afeta zero linhas **não é
 * sucesso**, e é relatada como tal.
 *
 * Antes de gravar, os impedimentos são conferidos e a operação é **recusada** se
 * houver algum (usuário técnico ausente, inativo ou sem a permissão do ERP para
 * aquela ação). Na simulação eles só são listados.
 *
 * Duas assimetrias entre aprovar e reprovar, ambas confirmadas na base:
 *  - aprovar NÃO muda `CD_STATUS` (a ordem segue PENDENTE, só ganha o carimbo);
 *  - reprovar move para REPROVADO e guarda o status anterior e o motivo.
 *
 * E uma assimetria de maturidade: a aprovação foi observada ao vivo (ordem
 * 40.975), a reprovação não. Por isso a reprovação só grava com
 * `questor.reprovacao_liberada` ligado — ver {@see self::assertReleased()}.
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
     * Autoriza uma ordem — de verdade, se o dry run estiver desligado.
     *
     * @param  bool  $simular  força a simulação mesmo com a gravação ligada.
     *                         É o que mantém o `questor:testar` inofensivo.
     * @return array<string, mixed> resultado no formato de {@see self::decide()}
     *
     * @throws QuestorException
     */
    public function approve(int $cdOrdemCompra, bool $simular = false): array
    {
        QuestorGate::ensureEnabled();

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

        return $this->decide(self::ACTION_APPROVE, $ordem, $sql, $bindings, [
            'CD_USUARIO_AUTORIZOU' => $this->technicalUserCode(),
            'DT_AUTORIZACAO' => 'GETDATE() — relógio do servidor do Questor',
            'CD_STATUS' => (int) $ordem->CD_STATUS . ' (inalterado: autorizar não muda o status)',
        ], [
            'CD_ORDEM_COMPRA = ?' => $cdOrdemCompra,
            'CD_STATUS = ?' => $this->pendingStatus(),
            'CD_USUARIO_AUTORIZOU IS NULL' => null,
        ], simular: $simular);
    }

    /**
     * Reprova uma ordem.
     *
     * O motivo é truncado em `questor.motivo_max` porque `DS_MOTIVO_REPROVADO`
     * é varchar(100) — sem isso o SQL Server recusaria a linha inteira. O texto
     * completo fica na trilha da Lara.
     *
     * @param  bool  $simular  força a simulação mesmo com a gravação ligada
     * @return array<string, mixed>
     *
     * @throws QuestorException
     */
    public function reject(int $cdOrdemCompra, string $motivo, bool $simular = false): array
    {
        QuestorGate::ensureEnabled();

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new QuestorException('Informe o motivo da reprovação.');
        }

        $motivoCompleto = $motivo;
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

        return $this->decide(self::ACTION_REJECT, $ordem, $sql, $bindings, [
            'CD_STATUS' => $this->rejectedStatus() . ' (REPROVADO)',
            'CD_STATUS_ANTERIOR' => $this->pendingStatus(),
            'CD_USUARIO_REPROVOU' => $this->technicalUserCode(),
            'DT_REPROVACAO' => 'GETDATE() — relógio do servidor do Questor',
            'DS_MOTIVO_REPROVADO' => $motivo,
        ], [
            'CD_ORDEM_COMPRA = ?' => $cdOrdemCompra,
            'CD_STATUS = ?' => $this->pendingStatus(),
            'CD_USUARIO_REPROVOU IS NULL' => null,
        ], ressalvas: [
            // O caminho de reprovação só tem evidência histórica (28 casos). Até
            // o teste ao vivo da seção 6.2 da especificação ser feito, quem lê o
            // resultado precisa saber disso.
            'A reprovação foi montada a partir do padrão histórico da base; o teste ao vivo '
            . '(seção 6.2 da especificação) ainda não foi realizado.',
        ], motivo: $motivoCompleto, simular: $simular);
    }

    /**
     * O caminho comum: confere, e então simula ou grava.
     *
     * @param  array<int, mixed>  $bindings
     * @param  array<string, mixed>  $depois  campos e valores que o UPDATE gravaria
     * @param  array<string, mixed>  $predicado  condições do WHERE, para a contagem
     * @param  array<int, string>  $ressalvas
     * @return array{acao: string, cd_ordem_compra: int, executado: bool, dry_run: bool,
     *               sql: string, bindings: array<int, mixed>, antes: array<string, mixed>,
     *               depois: array<string, mixed>, linhas_afetadas: int,
     *               impedimentos: array<int, string>, ressalvas: array<int, string>,
     *               usuario_tecnico: object|null, confirmacao: array<string, mixed>|null}
     *
     * @throws QuestorException
     */
    private function decide(
        string $acao,
        object $ordem,
        string $sql,
        array $bindings,
        array $depois,
        array $predicado,
        array $ressalvas = [],
        ?string $motivo = null,
        bool $simular = false,
    ): array {
        $simulando = $simular || QuestorGate::dryRun();

        $tecnico = $this->orders->technicalUser();
        $linhas = $this->matchingRows($predicado);
        $impedimentos = $this->blockers($ordem, $acao, $linhas, $tecnico);

        $resultado = [
            'acao' => $acao,
            'cd_ordem_compra' => (int) $ordem->CD_ORDEM_COMPRA,
            'executado' => false,
            'dry_run' => $simulando,
            'sql' => $sql,
            'bindings' => $bindings,
            'antes' => $this->snapshot($ordem),
            'depois' => $depois,
            'linhas_afetadas' => $linhas,
            'impedimentos' => $impedimentos,
            'ressalvas' => $ressalvas,
            'usuario_tecnico' => $tecnico,
            'confirmacao' => null,
        ];

        if ($simulando) {
            Log::info('Questor: simulação de ' . $acao, [
                'cd_ordem_compra' => $resultado['cd_ordem_compra'],
                'linhas_afetadas' => $linhas,
                'impedimentos' => $impedimentos,
                'user_id' => auth()->id(),
            ]);

            return $resultado;
        }

        $this->assertReleased($acao);

        // Um impedimento é motivo para NÃO gravar. Na simulação eles são
        // informativos; aqui são a última barreira antes do ERP.
        if ($impedimentos !== []) {
            throw new QuestorException(
                'A gravação foi recusada antes de chegar ao Questor: ' . implode(' ', $impedimentos)
            );
        }

        return $this->execute($resultado, $ordem, $sql, $bindings, $motivo);
    }

    /**
     * Grava no ERP, confere o resultado relendo a ordem e registra a trilha.
     *
     * A trilha é escrita **depois** do UPDATE e fora de qualquer transação com
     * ele — são bancos diferentes, em servidores diferentes, e não há transação
     * distribuída aqui. A ordem dos dois passos é deliberada: se a auditoria
     * falhar, o log ainda guarda o que aconteceu; se fosse ao contrário, uma
     * falha no ERP deixaria uma linha de auditoria de algo que não ocorreu.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<int, mixed>  $bindings
     * @return array<string, mixed>
     *
     * @throws QuestorException
     */
    private function execute(array $resultado, object $ordem, string $sql, array $bindings, ?string $motivo): array
    {
        try {
            $afetadas = DB::connection(config('questor.connection'))->update($sql, $bindings);
        } catch (\Throwable $e) {
            report($e);

            Log::error('Questor: falha ao gravar ' . $resultado['acao'], [
                'cd_ordem_compra' => $resultado['cd_ordem_compra'],
                'user_id' => auth()->id(),
                'erro' => $e->getMessage(),
            ]);

            throw new QuestorException(
                'A gravação no Questor falhou e a ordem não foi alterada. '
                . 'Confira a ordem na tela nativa antes de tentar de novo.'
            );
        }

        $resultado['executado'] = true;
        $resultado['linhas_afetadas'] = $afetadas;

        // Relê a ordem: é a prova de que o carimbo entrou, e não a suposição de
        // que entrou porque o UPDATE não deu erro.
        try {
            $resultado['confirmacao'] = $this->snapshot($this->orders->find($resultado['cd_ordem_compra']));
        } catch (\Throwable $e) {
            report($e);
        }

        Log::warning('Questor: gravação efetiva de ' . $resultado['acao'], [
            'cd_ordem_compra' => $resultado['cd_ordem_compra'],
            'linhas_afetadas' => $afetadas,
            'questor_user' => $this->technicalUserCode(),
            'user_id' => auth()->id(),
        ]);

        $this->record($resultado, $ordem, $motivo);

        return $resultado;
    }

    /**
     * A linha de auditoria. Falhar aqui não desfaz o que já foi gravado no ERP,
     * então o erro é reportado e engolido: negar o resultado ao usuário faria
     * ele tentar de novo uma operação que já aconteceu.
     *
     * @param  array<string, mixed>  $resultado
     */
    private function record(array $resultado, object $ordem, ?string $motivo): void
    {
        try {
            QuestorOrderDecision::create([
                'cd_ordem_compra' => $resultado['cd_ordem_compra'],
                'cd_filial' => $ordem->CD_FILIAL ?? null,
                'action' => $resultado['acao'],
                'questor_user' => $this->technicalUserCode(),
                'decided_by' => auth()->id(),
                'decided_by_name' => auth()->user()?->name,
                'motivo' => $motivo,
                'vl_total' => $ordem->VL_TOTAL ?? null,
                'rows_affected' => $resultado['linhas_afetadas'],
                'executed' => true,
            ]);
        } catch (\Throwable $e) {
            report($e);

            Log::error('Questor: decisão gravada no ERP mas NÃO registrada na trilha', [
                'cd_ordem_compra' => $resultado['cd_ordem_compra'],
                'acao' => $resultado['acao'],
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Os campos de decisão da ordem, do jeito que estão agora.
     *
     * @return array<string, mixed>
     */
    private function snapshot(object $ordem): array
    {
        return [
            'CD_STATUS' => $ordem->CD_STATUS,
            'DS_STATUS' => $ordem->DS_STATUS ?? null,
            'CD_USUARIO_AUTORIZOU' => $ordem->CD_USUARIO_AUTORIZOU,
            'DT_AUTORIZACAO' => $ordem->DT_AUTORIZACAO,
            'CD_USUARIO_REPROVOU' => $ordem->CD_USUARIO_REPROVOU,
            'DT_REPROVACAO' => $ordem->DT_REPROVACAO,
            'DS_MOTIVO_REPROVADO' => $ordem->DS_MOTIVO_REPROVADO,
        ];
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
     *
     * @throws QuestorException
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
                'Não foi possível conferir a ordem no Questor antes de gravar.'
            );
        }

        return (int) ($linha->TOTAL ?? 0);
    }

    /**
     * O que impede a gravação de acontecer.
     *
     * Na simulação viram uma lista informativa; na gravação, uma recusa.
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
     * A trava por ação.
     *
     * A **aprovação** está liberada: o comportamento dela foi observado ao vivo
     * na base (ordem 40.975 — só `CD_USUARIO_AUTORIZOU` e `DT_AUTORIZACAO`
     * mudaram, nem status nem `DT_ATUALIZACAO`).
     *
     * A **reprovação** não foi. O que existe dela é o padrão de 28 casos
     * históricos, e três perguntas continuam sem resposta observada:
     * `CD_STATUS_ANTERIOR` é mesmo preenchido? `DT_ATUALIZACAO` muda neste caso?
     * Algum outro campo é tocado? Enquanto o teste da seção 6.2 não for feito,
     * ela só grava com `QUESTOR_REPROVACAO_LIBERADA=true` — uma decisão
     * consciente, não um efeito colateral de desligar o dry run.
     *
     * @throws QuestorException
     */
    private function assertReleased(string $acao): void
    {
        if ($acao === self::ACTION_REJECT && !config('questor.reprovacao_liberada', false)) {
            throw new QuestorException(
                'A gravação de reprovação ainda não foi liberada: o comportamento dela no Questor não foi '
                . 'observado ao vivo (seção 6.2 da especificação). Reprove uma ordem de teste pela tela nativa, '
                . 'compare a linha campo a campo e então ligue QUESTOR_REPROVACAO_LIBERADA=true. '
                . 'Enquanto isso, a reprovação continua disponível em simulação.'
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
