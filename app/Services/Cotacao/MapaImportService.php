<?php

namespace App\Services\Cotacao;

use App\Exceptions\CotacaoException;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoMapaLog;
use App\Models\User;
use App\Services\Questor\DTO\FornecedorHistoricoDTO;
use App\Services\Questor\DTO\SolicitacaoDTO;
use App\Services\Questor\DTO\SolicitacaoItemDTO;
use App\Services\Questor\DTO\UltimaCompraDTO;
use App\Services\Questor\QuestorCompraRepository;
use App\Services\Questor\QuestorSolicitacaoRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transforma uma Solicitação de Compra do Questor num mapa de cotação da Lara.
 *
 * O QUESTOR NÃO É TOCADO. Aqui só se lê de lá; tudo o que se grava vai para o
 * banco próprio, dentro de uma transação da conexão padrão.
 *
 * A ordem das operações é deliberada: primeiro TODAS as leituras do ERP, depois
 * a transação local. Manter uma transação aberta enquanto se espera um banco
 * remoto responder é o jeito conhecido de segurar lock à toa — e se o Questor
 * cair no meio, nada foi escrito ainda.
 */
class MapaImportService
{
    public function __construct(
        private readonly QuestorSolicitacaoRepository $solicitacoes,
        private readonly QuestorCompraRepository $compras,
    ) {
    }

    /**
     * Existe mapa não cancelado para esta solicitação?
     *
     * É a validação em aplicação do índice parcial que MySQL não tem. A tela
     * chama isto ANTES de importar, para oferecer "abrir o existente" em vez de
     * o comprador descobrir na segunda tela que refez o trabalho.
     */
    public function mapaAbertoDe(int $cdSolicitacao): ?CotacaoMapa
    {
        return CotacaoMapa::query()
            ->where('questor_solicitacao', $cdSolicitacao)
            ->abertos()
            ->latest('id')
            ->first();
    }

    /**
     * Cria o mapa a partir da solicitação, com o retrato da última compra de
     * cada item.
     *
     * @param  array<int, int>  $fornecedoresSugeridos  CD_ENTIDADE que o comprador escolheu virar coluna
     *
     * @throws CotacaoException quando a SC não existe, não tem itens ou já tem mapa aberto
     * @throws \App\Exceptions\QuestorException quando o ERP não responde
     */
    public function importar(
        int $cdSolicitacao,
        User $usuario,
        ?string $titulo = null,
        ?string $comprador = null,
        array $fornecedoresSugeridos = []
    ): CotacaoMapa {
        if ($existente = $this->mapaAbertoDe($cdSolicitacao)) {
            throw CotacaoException::mapaJaExiste($existente);
        }

        // --- Leituras do Questor (fora da transação) ------------------------

        $sc = $this->solicitacoes->buscar($cdSolicitacao);

        if ($sc === null) {
            throw CotacaoException::solicitacaoNaoEncontrada($cdSolicitacao);
        }

        $itens = $this->solicitacoes->itens($cdSolicitacao);

        if ($itens->isEmpty()) {
            throw CotacaoException::solicitacaoSemItens($cdSolicitacao);
        }

        // UMA consulta para a SC inteira. Ver QuestorCompraRepository: a
        // alternativa seria uma ida ao ERP por item.
        $ultimasCompras = $this->compras->ultimaCompraPorItem($cdSolicitacao);

        $colunas = $fornecedoresSugeridos !== []
            ? $this->colunasEscolhidas($cdSolicitacao, $fornecedoresSugeridos)
            : collect();

        // --- Gravação local (em transação) ----------------------------------

        return DB::transaction(function () use ($sc, $itens, $ultimasCompras, $colunas, $usuario, $titulo, $comprador) {
            $mapa = CotacaoMapa::create([
                'questor_solicitacao' => $sc->codigo,
                'questor_empresa' => $sc->empresa,
                'questor_filial' => $sc->filial,
                'titulo' => mb_substr(trim((string) $titulo) ?: $sc->tituloSugerido(), 0, 255),
                // Retratos: a SC pode ser editada no ERP depois, o mapa impresso não.
                'solicitante' => $sc->solicitante ? mb_substr($sc->solicitante, 0, 100) : null,
                'departamento' => $sc->departamento ? mb_substr($sc->departamento, 0, 100) : null,
                'comprador' => $comprador ? mb_substr($comprador, 0, 100) : mb_substr($usuario->name, 0, 100),
                'data_mapa' => Carbon::today(),
                'status' => CotacaoMapa::STATUS_RASCUNHO,
                'user_id' => $usuario->id,
            ]);

            $ordem = 0;

            foreach ($itens as $item) {
                $ordem++;
                $mapa->itens()->create($this->linhaDoItem($item, $ultimasCompras->get($item->item), $ordem));
            }

            $posicao = 0;

            foreach ($colunas as $coluna) {
                $posicao++;
                $mapa->fornecedores()->create($coluna->paraColuna() + ['ordem' => $posicao]);
            }

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_IMPORTACAO, [
                'solicitacao' => $sc->codigo,
                'itens' => $itens->count(),
                'itens_sem_cadastro' => $itens->filter(fn (SolicitacaoItemDTO $i) => $i->semCadastro())->count(),
                'itens_com_historico' => $ultimasCompras->filter(fn (UltimaCompraDTO $u) => $u->encontrada())->count(),
                'fornecedores' => $colunas->count(),
            ], $usuario);

            return $mapa->load(['itens', 'fornecedores']);
        });
    }

    /**
     * Regrava o retrato da última compra de todos os itens do mapa.
     *
     * O retrato é congelado na importação de propósito — o mapa tem de ser
     * reproduzível meses depois. Quem quiser o dado de hoje pede aqui, e a ação
     * fica no log justamente porque muda a base de comparação de um mapa que
     * talvez já tenha preços digitados.
     *
     * @return int quantos itens tiveram o retrato alterado
     *
     * @throws CotacaoException quando o mapa não aceita mais edição
     */
    public function atualizarHistorico(CotacaoMapa $mapa, User $usuario): int
    {
        if (! $mapa->editavel()) {
            throw CotacaoException::mapaSomenteLeitura($mapa);
        }

        $ultimasCompras = $this->compras->ultimaCompraPorItem($mapa->questor_solicitacao);

        $alterados = 0;

        DB::transaction(function () use ($mapa, $ultimasCompras, $usuario, &$alterados) {
            foreach ($mapa->itens as $item) {
                // Item avulso não veio da SC e não tem CD_ITEM para casar.
                if ($item->questor_cd_item === null) {
                    continue;
                }

                $novo = $this->retratoDaUltimaCompra($ultimasCompras->get($item->questor_cd_item));

                $item->fill($novo);

                if ($item->isDirty()) {
                    $item->save();
                    $alterados++;
                }
            }

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_ATUALIZOU_HISTORICO, [
                'itens_alterados' => $alterados,
            ], $usuario);
        });

        return $alterados;
    }

    /**
     * Traz para o mapa o que mudou nos itens da SC depois da importação.
     *
     * Acontece de a SC ganhar itens no Questor com o mapa já em cotação. Aqui o
     * item novo entra no fim da grade (com o retrato da última compra, como na
     * importação) e o item que já estava no mapa recebe descrição, unidade,
     * quantidade e código de material atuais. Preços digitados e vencedor não
     * são tocados.
     *
     * Duas coisas NÃO acontecem, de propósito:
     *  - item que saiu da SC não é apagado: pode ter preço digitado. Volta na
     *    lista `fora_da_sc` para o comprador decidir se remove;
     *  - item que o comprador removeu do mapa não volta: a remoção grava o
     *    `questor_cd_item` na trilha, e é por ela que ele é reconhecido.
     *
     * @return array{incluidos: array<int, string>, alterados: array<int, string>, fora_da_sc: array<int, string>}
     *
     * @throws CotacaoException quando o mapa não aceita mais edição ou a SC sumiu
     * @throws \App\Exceptions\QuestorException quando o ERP não responde
     */
    public function atualizarItens(CotacaoMapa $mapa, User $usuario): array
    {
        if (! $mapa->editavel()) {
            throw CotacaoException::mapaSomenteLeitura($mapa);
        }

        // --- Leituras do Questor (fora da transação) ------------------------

        $itensDaSc = $this->solicitacoes->itens($mapa->questor_solicitacao);

        // SC vazia é mais provável de ser leitura errada (ou SC apagada) do que
        // "todos os itens saíram" — e não há nada a acrescentar mesmo.
        if ($itensDaSc->isEmpty()) {
            throw CotacaoException::solicitacaoSemItens($mapa->questor_solicitacao);
        }

        $ultimasCompras = $this->compras->ultimaCompraPorItem($mapa->questor_solicitacao);

        // --- Gravação local (em transação) ----------------------------------

        return DB::transaction(function () use ($mapa, $itensDaSc, $ultimasCompras, $usuario) {
            $doMapa = $mapa->itens()
                ->whereNotNull('questor_cd_item')
                ->get()
                ->keyBy('questor_cd_item');

            $removidos = $this->itensRemovidosPeloComprador($mapa);
            $ordem = (int) $mapa->itens()->max('ordem');

            $resultado = ['incluidos' => [], 'alterados' => [], 'fora_da_sc' => []];

            foreach ($itensDaSc as $daSc) {
                /** @var SolicitacaoItemDTO $daSc */
                $existente = $doMapa->get($daSc->item);

                if ($existente === null) {
                    if (in_array($daSc->item, $removidos, true)) {
                        continue;
                    }

                    $ordem++;
                    $novo = $mapa->itens()->create(
                        $this->linhaDoItem($daSc, $ultimasCompras->get($daSc->item), $ordem)
                    );
                    $resultado['incluidos'][$novo->id] = $novo->descricao;

                    continue;
                }

                $existente->fill(array_intersect_key(
                    $this->linhaDoItem($daSc, null, $existente->ordem),
                    array_flip(['descricao', 'unidade', 'quantidade', 'questor_cd_material'])
                ));

                if ($existente->isDirty()) {
                    $existente->save();
                    $resultado['alterados'][$existente->id] = $existente->descricao;
                }
            }

            $naSc = $itensDaSc->map(fn (SolicitacaoItemDTO $i) => $i->item)->all();

            foreach ($doMapa as $cdItem => $item) {
                if (! in_array((int) $cdItem, $naSc, true)) {
                    $resultado['fora_da_sc'][$item->id] = $item->descricao;
                }
            }

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_ATUALIZOU_ITENS, [
                'solicitacao' => $mapa->questor_solicitacao,
                'incluidos' => array_values($resultado['incluidos']),
                'alterados' => array_values($resultado['alterados']),
                'fora_da_sc' => array_values($resultado['fora_da_sc']),
            ], $usuario);

            return $resultado;
        });
    }

    /**
     * Os CD_ITEM que o comprador tirou do mapa, lidos da trilha.
     *
     * Remoções anteriores a 29/10/2026 não gravavam o código e não entram aqui:
     * esses itens voltariam numa atualização — e a mensagem da tela diz quais
     * entraram, para o comprador tirar de novo.
     *
     * @return array<int, int>
     */
    private function itensRemovidosPeloComprador(CotacaoMapa $mapa): array
    {
        return CotacaoMapaLog::query()
            ->where('cotacao_mapa_id', $mapa->id)
            ->where('acao', CotacaoMapaLog::ACAO_ITEM)
            ->get()
            ->filter(fn (CotacaoMapaLog $log) => ($log->payload['evento'] ?? null) === 'removido'
                && isset($log->payload['questor_cd_item']))
            ->map(fn (CotacaoMapaLog $log) => (int) $log->payload['questor_cd_item'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Fornecedores candidatos a virar coluna, agregados por fornecedor.
     *
     * A query 5.c devolve uma linha por (item, fornecedor); o comprador precisa
     * ver "este fornecedor já forneceu 4 dos 6 itens", que é o que decide se
     * vale a pena pedir cotação a ele.
     *
     * @return Collection<int, array<string, mixed>> ordenada por cobertura e recência
     */
    public function sugestoesDeFornecedor(int $cdSolicitacao): Collection
    {
        return $this->compras->fornecedoresDaSolicitacao($cdSolicitacao)
            ->groupBy(fn (FornecedorHistoricoDTO $f) => $f->codigo)
            ->map(function (Collection $linhas) {
                /** @var FornecedorHistoricoDTO $primeiro */
                $primeiro = $linhas->first();

                return [
                    'codigo' => $primeiro->codigo,
                    'nome' => $primeiro->nomeCurto(),
                    'razao_social' => $primeiro->nome,
                    'cnpj' => $primeiro->cnpj,
                    'telefone' => $primeiro->telefone,
                    'email' => $primeiro->email,
                    'ativo' => $primeiro->ativo,
                    'itens_atendidos' => $linhas->count(),
                    'compras' => $linhas->sum(fn (FornecedorHistoricoDTO $f) => $f->qtdCompras),
                    'ultima_compra' => $linhas->max(fn (FornecedorHistoricoDTO $f) => $f->ultimaCompra),
                    'itens' => $linhas->pluck('item')->filter()->values()->all(),
                ];
            })
            ->sortByDesc(fn (array $f) => [$f['itens_atendidos'], $f['ultima_compra']?->timestamp ?? 0])
            ->values();
    }

    /**
     * Os DTOs dos fornecedores que o comprador marcou, na ordem em que ele os
     * marcou.
     *
     * @param  array<int, int>  $codigos
     * @return Collection<int, FornecedorHistoricoDTO>
     */
    private function colunasEscolhidas(int $cdSolicitacao, array $codigos): Collection
    {
        $codigos = collect($codigos)->map(fn ($c) => (int) $c)->unique();

        $limite = max(1, (int) config('questor.cotacao.max_fornecedores', 10));

        if ($codigos->count() > $limite) {
            throw CotacaoException::limiteDeFornecedores($limite);
        }

        $porCodigo = $this->compras->fornecedoresDaSolicitacao($cdSolicitacao)
            ->keyBy(fn (FornecedorHistoricoDTO $f) => $f->codigo);

        return $codigos
            ->map(fn (int $codigo) => $porCodigo->get($codigo))
            ->filter()
            ->values();
    }

    /**
     * A linha do mapa correspondente a um item da SC.
     *
     * @return array<string, mixed>
     */
    private function linhaDoItem(SolicitacaoItemDTO $item, ?UltimaCompraDTO $ultima, int $ordem): array
    {
        return [
            'questor_cd_item' => $item->item,
            // Segue nulo quando o item foi digitado como texto livre. A linha
            // entra no mapa igual — só não tem histórico por código.
            'questor_cd_material' => $item->material,
            'descricao' => mb_substr($item->descricao, 0, 255),
            'unidade' => $item->unidade ? mb_substr($item->unidade, 0, 10) : null,
            'quantidade' => $item->quantidade,
            'ordem' => $ordem,
            'origem' => CotacaoMapaItem::ORIGEM_SOLICITACAO,
        ] + $this->retratoDaUltimaCompra($ultima);
    }

    /**
     * Os campos `ult_compra_*` a partir do DTO — ou todos nulos quando não há
     * compra anterior.
     *
     * Zerar em vez de deixar nulo importa: um item que perdeu o histórico (ou
     * cujo item avulso nunca teve) não pode ficar com o retrato antigo de outro
     * item depois de um "atualizar histórico".
     *
     * @return array<string, mixed>
     */
    private function retratoDaUltimaCompra(?UltimaCompraDTO $ultima): array
    {
        if ($ultima === null || ! $ultima->encontrada()) {
            return [
                'ult_compra_data' => null,
                'ult_compra_fornecedor_id' => null,
                'ult_compra_fornecedor_nome' => null,
                'ult_compra_valor' => null,
                'ult_compra_nf' => null,
                'ult_compra_unidade' => null,
            ];
        }

        return [
            'ult_compra_data' => $ultima->data,
            'ult_compra_fornecedor_id' => $ultima->fornecedorCodigo,
            'ult_compra_fornecedor_nome' => $ultima->fornecedorCurto()
                ? mb_substr($ultima->fornecedorCurto(), 0, 150)
                : null,
            'ult_compra_valor' => $ultima->valorUnitario,
            'ult_compra_nf' => $ultima->notaFiscal ? mb_substr($ultima->notaFiscal, 0, 20) : null,
            'ult_compra_unidade' => $ultima->unidade ? mb_substr($ultima->unidade, 0, 10) : null,
        ];
    }

    /**
     * Acrescenta uma coluna de fornecedor ao mapa já criado.
     *
     * É a alteração que o comprador pediu explicitamente: dentro da Lara ele
     * pode acrescentar fornecedores à cotação a qualquer momento — inclusive um
     * que não existe no cadastro do Questor, caso em que `questor_cd_entidade`
     * fica nulo.
     *
     * @param  array<string, mixed>  $dados
     *
     * @throws CotacaoException quando o mapa está fechado ou no limite de colunas
     */
    public function acrescentarFornecedor(CotacaoMapa $mapa, array $dados, User $usuario): CotacaoMapaFornecedor
    {
        if (! $mapa->editavel()) {
            throw CotacaoException::mapaSomenteLeitura($mapa);
        }

        $limite = max(1, (int) config('questor.cotacao.max_fornecedores', 10));

        if ($mapa->fornecedores()->count() >= $limite) {
            throw CotacaoException::limiteDeFornecedores($limite);
        }

        return DB::transaction(function () use ($mapa, $dados, $usuario) {
            $dados['ordem'] = (int) $mapa->fornecedores()->max('ordem') + 1;

            $fornecedor = $mapa->fornecedores()->create($dados);

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_FORNECEDOR, [
                'evento' => 'criado',
                'fornecedor_id' => $fornecedor->id,
                'nome' => $fornecedor->nome,
                'questor_cd_entidade' => $fornecedor->questor_cd_entidade,
            ], $usuario);

            return $fornecedor;
        });
    }
}
