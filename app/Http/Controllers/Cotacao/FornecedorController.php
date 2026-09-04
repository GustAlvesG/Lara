<?php

namespace App\Http\Controllers\Cotacao;

use App\Exceptions\CotacaoException;
use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AtualizarCondicoesCotacaoRequest;
use App\Http\Requests\StoreCotacaoFornecedorRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaFornecedor;
use App\Models\CotacaoMapaLog;
use App\Services\Cotacao\MapaImportService;
use App\Services\Questor\QuestorCadastroRepository;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * As colunas do mapa — os fornecedores consultados.
 *
 * Aqui mora a alteração pedida sobre o desenho original: **dentro da Lara, o
 * comprador pode acrescentar fornecedores à cotação a qualquer momento**,
 * inclusive um que não existe no cadastro do Questor. A sugestão automática
 * (quem já forneceu o item) é ponto de partida, não limite: a cotação boa
 * frequentemente vem de quem nunca vendeu para a empresa.
 *
 * Quando o fornecedor vem do cadastro do ERP, `questor_cd_entidade` guarda o
 * CD_ENTIDADE; quando não vem, fica nulo e a coluna vive só pelo nome.
 */
class FornecedorController extends Controller
{
    /*
     | O `Controller` base deste projeto é vazio: as demais telas resolvem acesso
     | por middleware de permissão na rota. Aqui isso não basta — as ações têm
     | permissões diferentes E o estado do mapa entra na decisão (fechado é
     | somente leitura), o que é trabalho de policy. O trait entra por
     | controller, e não no base, para não mudar o comportamento das outras.
     */
    use AuthorizesRequests;

    public function __construct(
        private readonly MapaImportService $importacao,
        private readonly QuestorCadastroRepository $cadastros,
    ) {
    }

    /**
     * Acrescenta uma coluna ao mapa.
     */
    public function store(StoreCotacaoFornecedorRequest $request, CotacaoMapa $mapa)
    {
        $this->authorize('update', $mapa);

        try {
            $fornecedor = $this->importacao->acrescentarFornecedor(
                $mapa,
                $request->paraGravacao(),
                $request->user(),
            );
        } catch (CotacaoException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Fornecedor \"{$fornecedor->nome}\" acrescentado à cotação.");
    }

    /**
     * Edita a coluna: condições (frete, prazo, pagamento), contato, frete em
     * reais e desconto.
     */
    public function update(
        StoreCotacaoFornecedorRequest $request,
        CotacaoMapa $mapa,
        CotacaoMapaFornecedor $fornecedor
    ) {
        $this->authorize('update', $mapa);
        abort_unless($fornecedor->cotacao_mapa_id === $mapa->id, 404);

        DB::transaction(function () use ($request, $mapa, $fornecedor) {
            $anterior = $fornecedor->only([
                'nome', 'frete', 'prazo_entrega', 'condicao_pagamento', 'valor_frete', 'desconto',
            ]);

            $fornecedor->update($request->paraGravacao());

            $mudancas = array_filter(
                $fornecedor->only(array_keys($anterior)),
                fn ($valor, $campo) => (string) $valor !== (string) $anterior[$campo],
                ARRAY_FILTER_USE_BOTH
            );

            // Frete e desconto entram no total — mudança neles muda a decisão
            // de compra, então a trilha registra o antes e o depois.
            if ($mudancas !== []) {
                CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_FORNECEDOR, [
                    'evento' => 'alterado',
                    'fornecedor_id' => $fornecedor->id,
                    'de' => array_intersect_key($anterior, $mudancas),
                    'para' => $mudancas,
                ], $request->user());
            }
        });

        return back()->with('success', 'Coluna atualizada.');
    }

    /**
     * Salva as condições de TODAS as colunas de uma vez.
     *
     * É o "salvar geral" da tela. Cotação se anota de uma vez: o comprador
     * volta do telefone com frete, prazo e pagamento de vários fornecedores, e
     * um botão por linha fazia dele o responsável por lembrar de clicar em
     * todos — esquecer um deixava o mapa com uma condição velha sem nada avisar.
     *
     * TUDO NUMA TRANSAÇÃO, e um id de outro mapa reprova o lote inteiro (a
     * guarda está no request): salvar metade das colunas e responder "salvo"
     * seria pior do que recusar.
     *
     * A trilha registra UM evento com todas as mudanças, e não um por coluna:
     * foi um ato só do comprador, e é assim que ele vai procurar depois.
     */
    public function atualizarCondicoes(AtualizarCondicoesCotacaoRequest $request, CotacaoMapa $mapa)
    {
        $this->authorize('update', $mapa);

        $lote = $request->paraGravacao();

        $campos = ['nome', 'frete', 'prazo_entrega', 'condicao_pagamento', 'valor_frete', 'desconto'];

        $alteradas = DB::transaction(function () use ($request, $mapa, $lote, $campos) {
            // Uma consulta só, já restrita ao mapa: a guarda de escopo do
            // request confere os ids, e o `whereKey` aqui é a segunda tranca.
            $colunas = $mapa->fornecedores()->whereKey(array_keys($lote))->get()->keyBy('id');

            $mudancas = [];

            foreach ($lote as $id => $dados) {
                $fornecedor = $colunas->get($id);

                if ($fornecedor === null) {
                    continue;
                }

                $anterior = $fornecedor->only($campos);

                $fornecedor->fill($dados);

                // `isDirty` em vez de comparar tudo: sem isso a trilha ganharia
                // um registro a cada clique no botão, inclusive quando nada
                // mudou, e o histórico do mapa viraria ruído.
                if (! $fornecedor->isDirty()) {
                    continue;
                }

                $fornecedor->save();

                $depois = array_intersect_key($fornecedor->only($campos), $fornecedor->getChanges());

                $mudancas[] = [
                    'fornecedor_id' => (int) $id,
                    'nome' => $fornecedor->nome,
                    'de' => array_intersect_key($anterior, $depois),
                    'para' => $depois,
                ];
            }

            if ($mudancas !== []) {
                CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_FORNECEDOR, [
                    'evento' => 'condicoes_em_lote',
                    'colunas_alteradas' => count($mudancas),
                    'mudancas' => $mudancas,
                ], $request->user());
            }

            return count($mudancas);
        });

        if ($alteradas === 0) {
            return back()->with('success', 'Nada mudou nas condições — nenhum registro novo no histórico.');
        }

        return back()->with('success', $alteradas === 1
            ? 'Condições salvas: 1 coluna alterada.'
            : "Condições salvas: {$alteradas} colunas alteradas.");
    }

    /**
     * Remove a coluna — e com ela os preços já digitados naquele fornecedor.
     */
    public function destroy(Request $request, CotacaoMapa $mapa, CotacaoMapaFornecedor $fornecedor)
    {
        $this->authorize('update', $mapa);
        abort_unless($fornecedor->cotacao_mapa_id === $mapa->id, 404);

        DB::transaction(function () use ($request, $mapa, $fornecedor) {
            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_FORNECEDOR, [
                'evento' => 'removido',
                'fornecedor_id' => $fornecedor->id,
                'nome' => $fornecedor->nome,
                // A contagem explica, depois, por que o mapa perdeu preços.
                'precos_removidos' => $fornecedor->precos()->count(),
                'itens_que_vencia' => $fornecedor->itensVencidos()->count(),
            ], $request->user());

            // Os itens em que ele era o vencedor ficam sem decisão (a FK é
            // nullOnDelete) — a linha do item continua no mapa.
            $fornecedor->delete();
        });

        return back()->with('success', "Coluna \"{$fornecedor->nome}\" removida.");
    }

    /**
     * Autocomplete de fornecedor no cadastro do Questor (query 9.d).
     *
     * Devolve JSON: é digitação ao vivo. Uma lista vazia aqui não impede nada —
     * o comprador segue e cadastra o fornecedor só pelo nome.
     */
    public function buscar(Request $request)
    {
        $this->authorize('create', CotacaoMapa::class);

        $termo = trim((string) $request->query('q'));

        try {
            $resultados = $this->cadastros->buscarFornecedores($termo);
        } catch (QuestorException $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage(), 'itens' => []]);
        }

        return response()->json([
            'ok' => true,
            'itens' => $resultados->map(fn (object $f) => [
                'codigo' => (int) $f->CD_ENTIDADE,
                'nome' => trim((string) ($f->DS_FANTASIA ?: $f->DS_ENTIDADE)),
                'razao_social' => trim((string) $f->DS_ENTIDADE),
                'cnpj' => trim((string) $f->NR_CPFCNPJ),
                'telefone' => trim((string) $f->NR_TELEFONE),
                'email' => trim((string) ($f->DS_EMAIL_ORD_COMPRA ?: $f->DS_EMAIL)),
                'cidade' => trim((string) ($f->DS_CIDADE ?? '')),
                'uf' => trim((string) ($f->DS_UF ?? '')),
            ])->values(),
        ]);
    }

    /**
     * Listas de apoio para os campos de condição (queries 9.a–9.c).
     *
     * São SUGESTÃO, não restrição: o campo aceita texto livre, porque o mapa em
     * uso hoje tem "CONFIRMAR" e "3DU", que não existem nas tabelas do ERP.
     */
    public function condicoes()
    {
        $this->authorize('create', CotacaoMapa::class);

        try {
            return response()->json([
                'ok' => true,
                'fretes' => $this->cadastros->fretes()->pluck('DS_FRETE')->filter()->values(),
                'prazos' => $this->cadastros->prazosEntrega()->pluck('DS_PRAZO_ENTREGA')->filter()->values(),
                'pagamentos' => $this->cadastros->formasPagamento()
                    ->map(fn (object $f) => trim((string) ($f->DS_ABREVIACAO ?: $f->DS_FORMA_PAGAMENTO)))
                    ->filter()->values(),
            ]);
        } catch (QuestorException $e) {
            // Sem o ERP a tela perde o autocomplete e continua funcionando: os
            // três campos são texto livre de qualquer forma.
            return response()->json([
                'ok' => false,
                'erro' => $e->getMessage(),
                'fretes' => [], 'prazos' => [], 'pagamentos' => [],
            ]);
        }
    }
}
