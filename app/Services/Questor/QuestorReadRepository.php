<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Base dos repositórios que LEEM o Questor.
 *
 * A regra do módulo de cotação é inegociável: o banco do Questor
 * (FUNCSIDERURG) é somente leitura. Nada é gravado, alterado ou apagado lá.
 *
 * Esta classe é o único caminho até a conexão para quem herda dela, e ela só
 * sabe fazer três coisas: qualificar o nome da tabela, rodar um `SELECT` com
 * bindings e guardar o resultado em cache. Não existe aqui `insert`, `update`,
 * `statement` nem acesso ao objeto de conexão — quem quiser escrever no ERP
 * tem de sair deste caminho de propósito, e não por descuido.
 *
 * Isso é defesa em profundidade, não a defesa principal: **o login do banco
 * usado por este módulo precisa ter apenas SELECT**, concedido pelo DBA. Código
 * não é permissão de banco.
 *
 * Duas outras armadilhas do schema estão tratadas nos repositórios filhos, e
 * documentadas em docs/funcionalidades/cotacao-mapa.md:
 *   - CD_MATERIAL nulo na solicitação (item digitado como texto livre);
 *   - CD_STATUS da NF de entrada sem FK (todo join com TBL_STATUS é LEFT).
 */
abstract class QuestorReadRepository
{
    /**
     * Nome qualificado da tabela: `FUNCSIDERURG.dbo.TBL_...`.
     *
     * Qualificar é obrigatório e não estético: o login da Lara pode ter outro
     * banco como default, e um `TBL_COMPRAS_SOLICITACAO` sem qualificação
     * resolveria para o lugar errado sem dar erro.
     */
    protected function table(string $nome): string
    {
        return sprintf(
            '%s.%s.%s',
            config('questor.database', 'FUNCSIDERURG'),
            config('questor.schema', 'dbo'),
            $nome
        );
    }

    /**
     * Roda um SELECT no Questor.
     *
     * Todo parâmetro entra por binding. Nenhum valor vindo de tela é
     * concatenado na string — nem código de solicitação, nem termo de busca.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<int, object>
     *
     * @throws QuestorException quando o módulo está desligado ou o ERP não responde
     */
    protected function select(string $sql, array $bindings = []): array
    {
        // A trava de escrita vem ANTES da checagem do módulo: uma instrução que
        // não é leitura deve ser recusada mesmo com a integração ligada,
        // desligada ou mal configurada. A ordem inversa faria a recusa depender
        // de uma flag de ambiente.
        $this->garantirSomenteLeitura($sql);

        QuestorGate::ensureEnabled();

        try {
            return DB::connection(config('questor.connection'))->select($sql, $bindings);
        } catch (\Throwable $e) {
            report($e);

            throw new QuestorException(
                'Não foi possível consultar o Questor. Confira a conexão com o ERP e tente novamente.'
            );
        }
    }

    /**
     * Mesma coisa, com cache.
     *
     * Existe para o histórico de compras: ele não muda durante uma cotação, e
     * reconsultá-lo a cada abertura de modal só castiga o ERP. TTL zero na
     * configuração desliga o cache sem mexer em código.
     *
     * @param  array<int, mixed>  $bindings
     * @return array<int, object>
     */
    protected function selectCached(string $chave, string $sql, array $bindings = []): array
    {
        $ttl = (int) config('questor.cotacao.cache_ttl', 900);

        if ($ttl <= 0) {
            return $this->select($sql, $bindings);
        }

        return Cache::remember(
            'questor.cotacao.' . $chave,
            $ttl,
            fn () => $this->select($sql, $bindings)
        );
    }

    /**
     * Trava de segurança: a instrução tem de começar por SELECT ou WITH.
     *
     * Não é um parser de SQL e não tenta ser — é o aviso que dispara se alguém,
     * um dia, montar um UPDATE por engano dentro de um repositório de leitura.
     *
     * @throws QuestorException
     */
    private function garantirSomenteLeitura(string $sql): void
    {
        $inicio = strtoupper(ltrim($sql, " \t\n\r\0\x0B;"));

        if (! str_starts_with($inicio, 'SELECT') && ! str_starts_with($inicio, 'WITH')) {
            throw new QuestorException(
                'Instrução recusada: o módulo de cotação só lê o Questor. '
                . 'Nenhuma escrita no ERP pode sair daqui.'
            );
        }
    }

    /**
     * O trecho de WHERE que descarta notas de entrada canceladas, junto com o
     * binding correspondente.
     *
     * O código do status NÃO é chutado: `TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS`
     * não tem FK declarada no Questor, então não há como deduzi-lo do schema.
     * Ele vem da configuração, descoberto por `php artisan cotacao:descobrir-config`.
     *
     * Sem configuração, o filtro simplesmente não entra: incluir uma nota
     * cancelada de vez em quando é menos grave do que esconder compras boas
     * porque alguém chutou o número errado.
     *
     * @param  string  $alias  alias da tabela de entrada na consulta
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function filtroNaoCancelada(string $alias = 'e'): array
    {
        $cancelado = config('questor.cotacao.status_nf_entrada_cancelada');

        if ($cancelado === null) {
            return ['', []];
        }

        return [" AND {$alias}.CD_STATUS <> ?", [(int) $cancelado]];
    }
}
