<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Leitura das ordens de compra no Questor (banco FUNCSIDERURG, SQL Server).
 *
 * **Só leitura.** Nenhum método desta classe escreve no ERP — a gravação (que
 * nesta versão apenas simula) mora em {@see QuestorAuthorizationWriter}.
 *
 * As consultas são as validadas contra a base de produção, com uma diferença
 * deliberada: as tabelas são qualificadas com banco e schema vindos da
 * configuração, porque o login da Lara pode ter outro banco como default e um
 * `TBL_COMPRAS_ORDEM_COMPRA` sem qualificação resolveria para o lugar errado.
 *
 * O que define "pendente de autorização" são três condições juntas — status
 * PENDENTE, sem autorizador e sem reprovador. Autorizar no Questor não muda o
 * status (confirmado em teste ao vivo): o que tira a ordem da fila é o carimbo
 * em CD_USUARIO_AUTORIZOU, não uma transição.
 */
class QuestorPurchaseOrders
{
    /**
     * Colunas do cabeçalho usadas na listagem e no detalhe. Explícitas de
     * propósito: a tabela tem dezenas de colunas e várias delas (valores de
     * frete, flags de bloqueio) não têm por que trafegar até a tela.
     */
    private const HEADER_COLUMNS = <<<'SQL'
        oc.CD_ORDEM_COMPRA,
        oc.CD_FILIAL,
        oc.CD_EMPRESA,
        oc.CD_STATUS,
        st.DS_STATUS,
        oc.CD_ENTIDADE,
        ent.DS_ENTIDADE      AS FORNECEDOR_RAZAO_SOCIAL,
        ent.DS_FANTASIA      AS FORNECEDOR_FANTASIA,
        ent.NR_CPFCNPJ       AS FORNECEDOR_CNPJ,
        ent.DS_EMAIL         AS FORNECEDOR_EMAIL,
        oc.CD_DEPARTAMENTO,
        dep.DS_DEPARTAMENTO,
        oc.DS_COMPRADOR,
        oc.DS_SOLICITANTE,
        oc.DS_REFERENTE,
        oc.DS_OBS,
        oc.VL_TOTAL,
        oc.NR_ITENS,
        oc.DT_CADASTRO,
        oc.DT_EMISSAO,
        oc.DT_ENTREGA_PREVISTA,
        oc.CD_USUARIO        AS CD_USUARIO_SOLICITANTE,
        oc.CD_USUARIO_AUTORIZOU,
        oc.DT_AUTORIZACAO,
        oc.CD_USUARIO_REPROVOU,
        oc.DT_REPROVACAO,
        oc.DS_MOTIVO_REPROVADO
    SQL;

    /**
     * A fila de aprovação: ordens pendentes que ninguém autorizou nem reprovou.
     *
     * @param  array{busca?: string|null, filial?: int|null}  $filtros
     * @return Collection<int, object>
     *
     * @throws QuestorException quando o Questor não responde
     */
    public function pending(array $filtros = []): Collection
    {
        [$where, $bindings] = $this->pendingPredicate();

        $busca = trim((string) ($filtros['busca'] ?? ''));

        if ($busca !== '') {
            // Número da ordem quando é só dígito; senão, fornecedor/solicitante.
            // Sem o teste numérico, digitar "40975" viraria um LIKE em texto e
            // não acharia a ordem 40.975.
            if (ctype_digit($busca)) {
                $where[] = 'oc.CD_ORDEM_COMPRA = ?';
                $bindings[] = (int) $busca;
            } else {
                $where[] = '(ent.DS_ENTIDADE LIKE ? OR ent.DS_FANTASIA LIKE ? OR oc.DS_SOLICITANTE LIKE ? OR oc.DS_REFERENTE LIKE ?)';
                $curinga = '%' . $busca . '%';
                array_push($bindings, $curinga, $curinga, $curinga, $curinga);
            }
        }

        $filial = $filtros['filial'] ?? null;

        if ($filial !== null && $filial !== '') {
            $where[] = 'oc.CD_FILIAL = ?';
            $bindings[] = (int) $filial;
        }

        $limite = max(1, (int) config('questor.limite_listagem', 200));

        $sql = sprintf(
            'SELECT TOP (%d) %s FROM %s ORDER BY oc.DT_CADASTRO ASC',
            $limite,
            self::HEADER_COLUMNS,
            $this->fromClause($where)
        );

        return collect($this->select($sql, $bindings));
    }

    /**
     * Cabeçalho de uma ordem. Sem filtro de estado: o detalhe também precisa
     * abrir uma ordem que acabou de sair da fila, para mostrar por quê.
     *
     * @throws QuestorException quando a ordem não existe
     */
    public function find(int $cdOrdemCompra): object
    {
        $sql = sprintf(
            'SELECT TOP (1) %s FROM %s WHERE oc.CD_ORDEM_COMPRA = ?',
            self::HEADER_COLUMNS,
            $this->fromClause()
        );

        $ordem = $this->select($sql, [$cdOrdemCompra])[0] ?? null;

        if ($ordem === null) {
            throw new QuestorException("Ordem de compra {$cdOrdemCompra} não encontrada no Questor.");
        }

        return $ordem;
    }

    /**
     * Itens da ordem.
     *
     * @return Collection<int, object>
     */
    public function items(int $cdOrdemCompra): Collection
    {
        $sql = sprintf(
            'SELECT it.CD_ITEM, it.CD_MATERIAL, it.DS_MATERIAL, it.DS_UNIDADE,
                    it.NR_QUANTIDADE, it.VL_UNITARIO, it.VL_TOTAL,
                    it.CD_CENTRO_CUSTO, it.DS_OBS, it.DT_ENTREGA
             FROM %s it
             WHERE it.CD_ORDEM_COMPRA = ?
             ORDER BY it.CD_ITEM',
            $this->table('TBL_COMPRAS_ORDEM_COMPRA_ITENS')
        );

        return collect($this->select($sql, [$cdOrdemCompra]));
    }

    /**
     * Contadores do topo da tela: quantas ordens esperam autorização e quanto
     * há parado nelas. Roda com o mesmo predicado da listagem, sem o TOP —
     * senão o total mentiria assim que a fila passasse do limite.
     *
     * @return array{quantidade: int, valor: float}
     */
    public function pendingSummary(): array
    {
        [$where, $bindings] = $this->pendingPredicate();

        $sql = sprintf(
            'SELECT COUNT(*) AS QUANTIDADE, COALESCE(SUM(oc.VL_TOTAL), 0) AS VALOR FROM %s',
            $this->fromClause($where)
        );

        $linha = $this->select($sql, $bindings)[0] ?? null;

        return [
            'quantidade' => (int) ($linha->QUANTIDADE ?? 0),
            'valor' => (float) ($linha->VALOR ?? 0),
        ];
    }

    /**
     * Filiais que aparecem na fila, para montar o filtro sem uma lista fixa no
     * código.
     *
     * @return Collection<int, int>
     */
    public function pendingBranches(): Collection
    {
        [$where, $bindings] = $this->pendingPredicate();

        $sql = sprintf(
            'SELECT DISTINCT oc.CD_FILIAL FROM %s ORDER BY oc.CD_FILIAL',
            $this->fromClause($where)
        );

        return collect($this->select($sql, $bindings))->map(fn($linha) => (int) $linha->CD_FILIAL);
    }

    /**
     * O usuário técnico configurado, como o Questor o enxerga. É a conferência
     * que evita descobrir só na hora de gravar que o código aponta para um
     * usuário inativo, inexistente ou sem permissão de autorizar.
     *
     * Devolve `null` quando não há usuário técnico configurado ou quando o
     * código configurado não existe no Questor.
     */
    public function technicalUser(): ?object
    {
        $codigo = config('questor.usuario_tecnico');

        if ($codigo === null) {
            return null;
        }

        $sql = sprintf(
            'SELECT TOP (1) u.CD_CODUSUARIO, u.DS_USUARIO, u.DS_LOGIN, u.X_ATIVO,
                    u.X_AUTORIZA_ORDEM_COMPRA, u.X_REPROVA_ORDEM_COMPRA
             FROM %s u WHERE u.CD_CODUSUARIO = ?',
            $this->table('TBL_USUARIOS')
        );

        return $this->select($sql, [(int) $codigo])[0] ?? null;
    }

    /**
     * Predicado da fila, compartilhado por listagem, contadores e filtro de
     * filial — para os três nunca discordarem sobre o que é "pendente".
     *
     * @return array{0: array<int, string>, 1: array<int, mixed>}
     */
    private function pendingPredicate(): array
    {
        $where = [
            'oc.CD_STATUS = ?',
            'oc.CD_USUARIO_AUTORIZOU IS NULL',
            'oc.CD_USUARIO_REPROVOU IS NULL',
        ];

        $bindings = [(int) config('questor.status.pendente', 1)];

        $filiais = (array) config('questor.filiais', []);

        if ($filiais !== []) {
            $where[] = 'oc.CD_FILIAL IN (' . implode(', ', array_fill(0, count($filiais), '?')) . ')';
            $bindings = array_merge($bindings, array_map('intval', $filiais));
        }

        // Corte histórico: sem ele a tela abre com as ~2.292 ordens que estão
        // pendentes há anos e nunca passaram por aprovação nenhuma.
        $desde = config('questor.desde');

        if (filled($desde)) {
            $where[] = 'oc.DT_CADASTRO >= ?';
            $bindings[] = $desde;
        }

        return [$where, $bindings];
    }

    /**
     * O FROM com os dois LEFT JOIN de contexto (fornecedor e departamento) e,
     * opcionalmente, o WHERE já embutido.
     *
     * @param  array<int, string>|null  $where
     */
    private function fromClause(?array $where = null): string
    {
        $sql = sprintf(
            '%s oc
             LEFT JOIN %s ent ON ent.CD_ENTIDADE = oc.CD_ENTIDADE
             LEFT JOIN %s dep ON dep.CD_DEPARTAMENTO = oc.CD_DEPARTAMENTO
             LEFT JOIN %s st  ON st.CD_STATUS = oc.CD_STATUS',
            $this->table('TBL_COMPRAS_ORDEM_COMPRA'),
            $this->table('TBL_ENTIDADES'),
            $this->table('TBL_DEPARTAMENTOS'),
            $this->table('TBL_STATUS'),
        );

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        return $sql;
    }

    /**
     * Nome qualificado da tabela: `FUNCSIDERURG.dbo.TBL_...`.
     */
    private function table(string $nome): string
    {
        return sprintf(
            '%s.%s.%s',
            config('questor.database', 'FUNCSIDERURG'),
            config('questor.schema', 'dbo'),
            $nome
        );
    }

    /**
     * Executa a consulta, com o módulo e a conexão conferidos antes.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<int, object>
     *
     * @throws QuestorException
     */
    private function select(string $sql, array $bindings): array
    {
        QuestorGate::ensureEnabled();

        try {
            return DB::connection(config('questor.connection'))->select($sql, $bindings);
        } catch (\Throwable $e) {
            report($e);

            throw new QuestorException(
                'Não foi possível consultar as ordens de compra no Questor. '
                . 'Confira a conexão com o ERP e tente novamente.'
            );
        }
    }
}
