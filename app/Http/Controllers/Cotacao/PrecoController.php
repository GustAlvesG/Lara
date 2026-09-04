<?php

namespace App\Http\Controllers\Cotacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalvarPrecoCotacaoRequest;
use App\Models\CotacaoMapa;
use App\Models\CotacaoMapaLog;
use App\Models\CotacaoPreco;
use App\Services\Cotacao\MapaCalculoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

/**
 * A digitação da grade: uma célula por vez.
 *
 * SALVAMENTO POR CÉLULA, sem botão "salvar tudo". Cotação é feita ao telefone,
 * um fornecedor de cada vez, ao longo de dias — um formulário que só grava no
 * fim perde tudo quando o navegador fecha, e obriga o comprador a lembrar o que
 * já digitou.
 *
 * A resposta devolve os totais recalculados para a tela atualizar rodapé,
 * destaques e cobertura sem recarregar a página nem refazer as contas em
 * JavaScript — as contas ficam num lugar só, no
 * {@see MapaCalculoService}.
 */
class PrecoController extends Controller
{
    /*
     | O `Controller` base deste projeto é vazio: as demais telas resolvem acesso
     | por middleware de permissão na rota. Aqui isso não basta — as ações têm
     | permissões diferentes E o estado do mapa entra na decisão (fechado é
     | somente leitura), o que é trabalho de policy. O trait entra por
     | controller, e não no base, para não mudar o comportamento das outras.
     */
    use AuthorizesRequests;

    public function __construct(private readonly MapaCalculoService $calculo)
    {
    }

    /**
     * Grava (ou atualiza) uma célula.
     *
     * O escopo — item e fornecedor pertencerem A ESTE mapa — é validado no
     * FormRequest. Sem isso, trocar um id no corpo da requisição escreveria no
     * mapa de outra pessoa.
     */
    public function salvar(SalvarPrecoCotacaoRequest $request, CotacaoMapa $mapa)
    {
        $this->authorize('editarPrecos', $mapa);

        $dados = $request->paraGravacao();

        $preco = DB::transaction(function () use ($request, $mapa, $dados) {
            $chave = [
                'cotacao_mapa_item_id' => $request->integer('item_id'),
                'cotacao_mapa_fornecedor_id' => $request->integer('fornecedor_id'),
            ];

            $anterior = CotacaoPreco::where($chave)->first();

            $preco = CotacaoPreco::updateOrCreate($chave, $dados);

            // Só registra o que de fato mudou: a grade salva a cada pausa de
            // digitação, e logar toda tecla encheria a trilha de ruído — o que
            // é o mesmo que não ter trilha.
            $mudou = $anterior === null
                || (string) $anterior->valor_unitario !== (string) $preco->valor_unitario
                || $anterior->situacao !== $preco->situacao;

            if ($mudou) {
                CotacaoMapaLog::registrar($mapa, CotacaoMapaLog::ACAO_PRECO, [
                    'item_id' => $preco->cotacao_mapa_item_id,
                    'fornecedor_id' => $preco->cotacao_mapa_fornecedor_id,
                    'de' => [
                        'valor' => $anterior?->valor_unitario,
                        'situacao' => $anterior?->situacao,
                    ],
                    'para' => [
                        'valor' => $preco->valor_unitario,
                        'situacao' => $preco->situacao,
                    ],
                ], $request->user());
            }

            return $preco;
        });

        $mapa->load(['itens.precos', 'fornecedores']);

        $calculado = $this->calculo->calcular(
            $mapa->itens,
            $mapa->fornecedores,
            $mapa->itens->flatMap(fn ($i) => $i->precos)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'preco' => [
                    'id' => $preco->id,
                    'item_id' => $preco->cotacao_mapa_item_id,
                    'fornecedor_id' => $preco->cotacao_mapa_fornecedor_id,
                    'valor_unitario' => $preco->valor_unitario,
                    'situacao' => $preco->situacao,
                ],
                'calculo' => $calculado,
            ]);
        }

        return back()->with('success', 'Preço salvo.');
    }
}
