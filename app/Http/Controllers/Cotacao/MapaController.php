<?php

namespace App\Http\Controllers\Cotacao;

use App\Exceptions\CotacaoException;
use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Cotacao\Concerns\RecalculaMapa;
use App\Http\Requests\DefinirVencedorCotacaoRequest;
use App\Http\Requests\ImportarMapaCotacaoRequest;
use App\Http\Requests\StoreCotacaoItemRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaItem;
use App\Models\CotacaoMapaLog;
use App\Services\Cotacao\MapaCalculoService;
use App\Services\Cotacao\MapaExportService;
use App\Services\Cotacao\MapaImportService;
use App\Services\Questor\QuestorCompraRepository;
use App\Services\Questor\QuestorGate;
use App\Services\Questor\QuestorSolicitacaoRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * O mapa de cotação: busca da solicitação, importação, grade e exportação.
 *
 * O Questor é lido, nunca escrito. Falha de integração vira mensagem na tela e
 * não 500 — quem abre isto está tentando fechar uma compra, e "o ERP não
 * respondeu" é uma resposta melhor que uma página de erro.
 */
class MapaController extends Controller
{
    /*
     | O `Controller` base deste projeto é vazio: as demais telas resolvem acesso
     | por middleware de permissão na rota. Aqui isso não basta — as ações têm
     | permissões diferentes E o estado do mapa entra na decisão (fechado é
     | somente leitura), o que é trabalho de policy. O trait entra por
     | controller, e não no base, para não mudar o comportamento das outras.
     */
    use AuthorizesRequests;
    use RecalculaMapa;

    public function __construct(
        private readonly QuestorSolicitacaoRepository $solicitacoes,
        private readonly QuestorCompraRepository $compras,
        private readonly MapaImportService $importacao,
        private readonly MapaCalculoService $calculo,
    ) {
    }

    /**
     * Lista os mapas e abre a busca de solicitação.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CotacaoMapa::class);

        $status = $request->query('status');

        $mapas = CotacaoMapa::query()
            ->when(filled($status), fn ($q) => $q->where('status', $status))
            ->when(filled($request->query('busca')), function ($q) use ($request) {
                $busca = trim((string) $request->query('busca'));

                // Número puro é o da SC — é assim que o comprador procura.
                if (ctype_digit($busca)) {
                    $q->where('questor_solicitacao', (int) $busca);
                } else {
                    $q->where(fn ($sub) => $sub
                        ->where('titulo', 'like', "%{$busca}%")
                        ->orWhere('solicitante', 'like', "%{$busca}%")
                        ->orWhere('comprador', 'like', "%{$busca}%"));
                }
            })
            ->withCount(['itens', 'fornecedores'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('cotacao.mapas.index', [
            'mapas' => $mapas,
            'filtros' => ['status' => $status, 'busca' => $request->query('busca')],
            'config' => QuestorGate::summary(),
        ]);
    }

    /**
     * Prévia de uma solicitação antes de gerar o mapa.
     *
     * Mostra cabeçalho, itens e os fornecedores que já forneceram cada item —
     * e, se já houver mapa aberto para a mesma SC, oferece abrir o existente em
     * vez de duplicar o trabalho.
     *
     * Também atende a busca por período (query 1.b), para quem não sabe o
     * número.
     */
    public function previa(Request $request)
    {
        $this->authorize('create', CotacaoMapa::class);

        $dados = [
            'config' => QuestorGate::summary(),
            'codigo' => $request->query('solicitacao'),
            'solicitacao' => null,
            'itens' => collect(),
            'ultimasCompras' => collect(),
            'sugestoes' => collect(),
            'mapaExistente' => null,
            'resultados' => collect(),
            'filtros' => [
                'de' => $request->query('de', Carbon::today()->subMonths(6)->format('Y-m-d')),
                'ate' => $request->query('ate', Carbon::today()->format('Y-m-d')),
                'busca' => $request->query('busca'),
            ],
            'erro' => null,
        ];

        try {
            $codigo = $request->query('solicitacao');

            if (filled($codigo) && ctype_digit((string) $codigo)) {
                $codigo = (int) $codigo;

                $dados['solicitacao'] = $this->solicitacoes->buscar($codigo);

                if ($dados['solicitacao'] === null) {
                    $dados['erro'] = "Solicitação {$codigo} não encontrada no Questor.";
                } else {
                    $dados['itens'] = $this->solicitacoes->itens($codigo);
                    $dados['ultimasCompras'] = $this->compras->ultimaCompraPorItem($codigo);
                    $dados['sugestoes'] = $this->importacao->sugestoesDeFornecedor($codigo);
                    $dados['mapaExistente'] = $this->importacao->mapaAbertoDe($codigo);
                }
            } elseif ($request->hasAny(['de', 'ate', 'busca'])) {
                $dados['resultados'] = $this->solicitacoes->procurar(
                    $dados['filtros']['de'],
                    $dados['filtros']['ate'],
                    $dados['filtros']['busca'],
                );
            }
        } catch (QuestorException $e) {
            $dados['erro'] = $e->getMessage();
        }

        return view('cotacao.mapas.previa', $dados);
    }

    /**
     * Gera o mapa a partir da solicitação.
     */
    public function store(ImportarMapaCotacaoRequest $request)
    {
        $this->authorize('create', CotacaoMapa::class);

        try {
            $mapa = $this->importacao->importar(
                (int) $request->integer('solicitacao'),
                $request->user(),
                $request->input('titulo'),
                $request->input('comprador'),
                $request->input('fornecedores', []) ?? [],
            );
        } catch (CotacaoException $e) {
            // Mapa já existente não é erro do comprador: é motivo para mandá-lo
            // ao mapa que já está lá.
            if ($e->mapa !== null) {
                return redirect()
                    ->route('cotacao.mapas.show', $e->mapa)
                    ->with('warning', $e->getMessage() . ' Você foi levado a ele.');
            }

            return back()->withInput()->with('error', $e->getMessage());
        } catch (QuestorException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('cotacao.mapas.show', $mapa)
            ->with('success', "Mapa gerado com {$mapa->itens->count()} item(ns) da solicitação {$mapa->questor_solicitacao}.");
    }

    /**
     * A grade: itens nas linhas, fornecedores nas colunas.
     */
    public function show(CotacaoMapa $mapa, MapaExportService $exportacao)
    {
        $this->authorize('view', $mapa);

        $mapa->load(['itens.precos', 'fornecedores', 'logs' => fn ($q) => $q->limit(50)]);

        $precos = $mapa->itens->flatMap(fn (CotacaoMapaItem $i) => $i->precos);

        return view('cotacao.mapas.show', [
            'mapa' => $mapa,
            'calculo' => $this->calculo->calcular($mapa->itens, $mapa->fornecedores, $precos),
            'config' => QuestorGate::summary(),
            // O menu de exportação se monta a partir daqui: acrescentar um
            // layout novo não pede alteração na view.
            'layouts' => $exportacao->layouts(),
        ]);
    }

    /**
     * Cabeçalho do mapa e mudança de situação.
     *
     * Fechar e reabrir passam por autorizações diferentes de editar o título —
     * fechar encerra a cotação, reabrir devolve um documento à edição.
     */
    public function update(Request $request, CotacaoMapa $mapa)
    {
        $dados = $request->validate([
            'titulo' => ['sometimes', 'string', 'max:255'],
            'comprador' => ['sometimes', 'nullable', 'string', 'max:100'],
            'observacoes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'in:rascunho,em_cotacao,fechado,cancelado'],
        ]);

        $novoStatus = $dados['status'] ?? null;

        if ($novoStatus !== null && $novoStatus !== $mapa->status) {
            $acao = match (true) {
                $novoStatus === CotacaoMapa::STATUS_FECHADO => 'fechar',
                $mapa->fechado() => 'reabrir',
                default => 'update',
            };

            $this->authorize($acao, $mapa);
        } else {
            $this->authorize('update', $mapa);
        }

        $anterior = $mapa->only(['titulo', 'comprador', 'status']);

        $mapa->update($dados);

        if ($novoStatus !== null && $novoStatus !== $anterior['status']) {
            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_STATUS, [
                'de' => $anterior['status'],
                'para' => $novoStatus,
            ], $request->user());
        }

        return back()->with('success', 'Mapa atualizado.');
    }

    /**
     * Regrava o retrato da última compra de todos os itens.
     */
    public function atualizarHistorico(Request $request, CotacaoMapa $mapa)
    {
        $this->authorize('update', $mapa);

        try {
            $alterados = $this->importacao->atualizarHistorico($mapa, $request->user());
        } catch (CotacaoException|QuestorException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $alterados > 0
            ? "Histórico atualizado: {$alterados} item(ns) mudaram de referência."
            : 'Histórico conferido — nenhuma compra nova desde a importação.');
    }

    /**
     * Drill-down do item: histórico de compras, quem já forneceu, quem está
     * homologado, cotações antigas do Questor e OCs em aberto.
     *
     * Devolve um pedaço de HTML porque é conteúdo de modal — a tela injeta o
     * retorno e pronto, sem uma segunda montagem em JavaScript.
     */
    public function historicoItem(CotacaoMapa $mapa, CotacaoMapaItem $item)
    {
        $this->authorize('view', $mapa);

        // Binding aninhado conferido à mão: a rota casa dois ids independentes,
        // e sem isto o item de outro mapa abriria por aqui.
        abort_unless($item->cotacao_mapa_id === $mapa->id, 404);

        $dados = [
            'mapa' => $mapa,
            'item' => $item,
            'historico' => collect(),
            'fornecedores' => collect(),
            'homologados' => collect(),
            'cotacoes' => collect(),
            'ordens' => collect(),
            'erro' => null,
        ];

        // A armadilha nº 1 do módulo, tratada no lugar em que ela aparece: item
        // sem CD_MATERIAL não tem histórico por código, e isso NÃO pode quebrar
        // a tela nem parecer falha de integração.
        if (! $item->temCadastroNoQuestor()) {
            return view('cotacao.mapas.partials.historico-item', $dados);
        }

        try {
            $material = (int) $item->questor_cd_material;

            $dados['historico'] = $this->compras->historicoDoMaterial($material);
            $dados['fornecedores'] = $this->compras->fornecedoresDoMaterial($material);
            $dados['homologados'] = $this->compras->fornecedoresHomologados($material);
            $dados['cotacoes'] = $this->compras->cotacoesAnteriores($material);
            $dados['ordens'] = $this->compras->ordensDoMaterial($material);
        } catch (QuestorException $e) {
            $dados['erro'] = $e->getMessage();
        }

        return view('cotacao.mapas.partials.historico-item', $dados);
    }

    /**
     * Item avulso — o que não veio na solicitação mas entra na mesma cotação.
     */
    public function storeItem(StoreCotacaoItemRequest $request, CotacaoMapa $mapa)
    {
        $this->authorize('update', $mapa);

        $item = DB::transaction(function () use ($request, $mapa) {
            $item = $mapa->itens()->create($request->safe()->all() + [
                'origem' => CotacaoMapaItem::ORIGEM_AVULSO,
                'ordem' => (int) $mapa->itens()->max('ordem') + 1,
            ]);

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_ITEM, [
                'evento' => 'criado',
                'item_id' => $item->id,
                'descricao' => $item->descricao,
            ], $request->user());

            return $item;
        });

        return back()->with('success', "Item \"{$item->descricao}\" acrescentado ao mapa.");
    }

    /**
     * Remove um item do mapa (com os preços já digitados nele).
     */
    public function destroyItem(Request $request, CotacaoMapa $mapa, CotacaoMapaItem $item)
    {
        $this->authorize('update', $mapa);
        abort_unless($item->cotacao_mapa_id === $mapa->id, 404);

        DB::transaction(function () use ($request, $mapa, $item) {
            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_ITEM, [
                'evento' => 'removido',
                'item_id' => $item->id,
                'descricao' => $item->descricao,
                // Quantos preços foram embora junto: é o dado que explica uma
                // queda de total depois.
                'precos_removidos' => $item->precos()->count(),
            ], $request->user());

            $item->delete();
        });

        return back()->with('success', 'Item removido do mapa.');
    }

    /**
     * A decisão: de quem comprar este item.
     */
    public function definirVencedor(DefinirVencedorCotacaoRequest $request, CotacaoMapa $mapa)
    {
        $this->authorize('definirVencedor', $mapa);

        $item = $mapa->itens()->findOrFail($request->integer('item_id'));
        $fornecedorId = $request->input('fornecedor_id');
        $anterior = $item->vencedor_id;

        DB::transaction(function () use ($request, $mapa, $item, $fornecedorId, $anterior) {
            $item->update(['vencedor_id' => $fornecedorId !== null ? (int) $fornecedorId : null]);

            CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_VENCEDOR, [
                'item_id' => $item->id,
                'descricao' => $item->descricao,
                'de' => $anterior,
                'para' => $item->vencedor_id,
            ], $request->user());
        });

        if ($request->expectsJson()) {
            // Os totais vêm junto com a decisão, e é isso que dispensa o reload
            // da página: escolher um vencedor mexe no subtotal dos escolhidos,
            // no pedido de cada loja, no frete da compra dividida e no verde do
            // empate. A tela recebe tudo recalculado PELO SERVIDOR — refazer
            // essas contas em JavaScript seria uma segunda implementação, com a
            // agravante de poder divergir da que gera o XLSX.
            return response()->json([
                'ok' => true,
                'vencedor_id' => $item->vencedor_id,
                'calculo' => $this->recalcular($mapa, $this->calculo),
            ]);
        }

        return back()->with('success', 'Decisão registrada.');
    }

    /**
     * Baixa o XLSX, no layout que o comprador escolheu.
     *
     * São dois: `classico` (a planilha que a compra imprime e assina) e
     * `completo` (o arquivo de análise, com resumo e decisão). O parâmetro é
     * opcional e cai no clássico quando vem ausente ou errado — um link velho
     * no meio de uma cotação deve entregar a planilha de sempre, não um erro.
     */
    public function exportar(Request $request, CotacaoMapa $mapa, MapaExportService $exportacao)
    {
        $this->authorize('exportar', $mapa);

        $layout = $exportacao->normalizar((string) $request->query('layout', MapaExportService::LAYOUT_PADRAO));

        $caminho = $exportacao->gerar($mapa, $layout);
        $nome = $exportacao->nomeArquivo($mapa, $layout);

        CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_EXPORTACAO, [
            'arquivo' => $nome,
            // Qual layout saiu importa na trilha: os dois arquivos circulam, e
            // depois alguém pergunta de qual deles veio o número da reunião.
            'layout' => $layout,
        ], $request->user());

        // O arquivo é anexo de uso único: sai com a resposta e some do disco.
        return response()->download($caminho, $nome)->deleteFileAfterSend();
    }

    /**
     * Descarta o mapa (soft delete — a trilha continua existindo).
     */
    public function destroy(CotacaoMapa $mapa)
    {
        $this->authorize('delete', $mapa);

        $mapa->delete();

        return redirect()->route('cotacao.mapas.index')
            ->with('success', "Mapa #{$mapa->id} descartado.");
    }
}
