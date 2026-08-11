<?php

namespace App\Http\Controllers\Questor;

use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Models\QuestorOrderDecision;
use App\Services\Questor\QuestorAuthorizationWriter;
use App\Services\Questor\QuestorGate;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Autorização de Ordem de Compra — a fila que vem do Questor.
 *
 * Lista as ordens pendentes, abre o detalhe com os itens e grava a decisão. Se
 * a gravação está ligada ou se a ação apenas simula é decisão da configuração
 * (`questor.dry_run`), não da rota: os mesmos endereços servem os dois modos, e
 * a tela diz em qual está.
 *
 * O fluxo de aprovação em vários níveis (alçadas, quem aprova o quê) ainda não
 * existe — hoje uma decisão na tela é a decisão final. O que já existe é a
 * trilha: cada gravação vira uma linha em `questor_order_decisions`, porque no
 * Questor só cabe um autorizador e ele é sempre o usuário técnico.
 *
 * Erros de integração viram mensagem na tela em vez de 500: quem abre isto está
 * tentando destravar uma compra, e "o Questor não respondeu" é uma resposta
 * melhor do que uma página de erro.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly QuestorPurchaseOrders $orders,
        private readonly QuestorAuthorizationWriter $writer,
    ) {
    }

    /**
     * A fila de ordens pendentes de autorização.
     */
    public function index(Request $request)
    {
        $filtros = [
            'busca' => $request->query('busca'),
            'filial' => $request->query('filial'),
        ];

        $dados = [
            'config' => QuestorGate::summary(),
            'filtros' => $filtros,
            'ordens' => new Collection,
            'resumo' => ['quantidade' => 0, 'valor' => 0.0],
            'filiais' => new Collection,
            'usuarioTecnico' => null,
            'erro' => null,
        ];

        try {
            $dados['ordens'] = $this->orders->pending($filtros);
            $dados['resumo'] = $this->orders->pendingSummary();
            $dados['filiais'] = $this->orders->pendingBranches();
            $dados['usuarioTecnico'] = $this->orders->technicalUser();
        } catch (QuestorException $e) {
            $dados['erro'] = $e->getMessage();
        }

        return view('questor.purchase-orders.index', $dados);
    }

    /**
     * Detalhe da ordem: cabeçalho, fornecedor e itens.
     */
    public function show(int $ordem)
    {
        try {
            return view('questor.purchase-orders.show', [
                'config' => QuestorGate::summary(),
                'ordem' => $this->orders->find($ordem),
                'itens' => $this->orders->items($ordem),
                'usuarioTecnico' => $this->orders->technicalUser(),
                'simulacao' => session('questor_simulacao'),
                // Quem decidiu na Lara — o Questor mostra só o usuário técnico.
                'decisoes' => QuestorOrderDecision::where('cd_ordem_compra', $ordem)
                    ->orderByDesc('id')
                    ->get(),
            ]);
        } catch (QuestorException $e) {
            return redirect()->route('questor.purchase-orders.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Autoriza a ordem — de verdade quando a gravação está ligada, em simulação
     * caso contrário. Quem decide é a configuração, não a rota.
     */
    public function approve(int $ordem)
    {
        return $this->decide(fn() => $this->writer->approve($ordem), $ordem);
    }

    /**
     * Reprova a ordem, com o motivo que vai para `DS_MOTIVO_REPROVADO`.
     */
    public function reject(Request $request, int $ordem)
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], [], ['motivo' => 'motivo da reprovação']);

        return $this->decide(fn() => $this->writer->reject($ordem, $dados['motivo']), $ordem);
    }

    /**
     * O caminho comum das duas decisões.
     *
     * A regra do aviso é a que interessa: **só é "sucesso" quando a gravação
     * aconteceu e pegou alguma linha**. Um UPDATE que afeta zero linhas não deu
     * erro nenhum, e é exatamente o caso que seria lido como "aprovei" quando na
     * verdade a ordem já tinha saído da fila.
     */
    private function decide(callable $acao, int $ordem)
    {
        try {
            $resultado = $acao();
        } catch (QuestorException $e) {
            return redirect()
                ->route('questor.purchase-orders.show', $ordem)
                ->with('error', $e->getMessage());
        }

        $rota = redirect()
            ->route('questor.purchase-orders.show', $ordem)
            ->with('questor_simulacao', $resultado);

        $verbo = $resultado['acao'] === QuestorAuthorizationWriter::ACTION_APPROVE
            ? 'autorizada'
            : 'reprovada';

        if (!$resultado['executado']) {
            return $resultado['impedimentos'] === []
                ? $rota->with('success', 'Simulação concluída — nada foi gravado no Questor. A instrução pegaria '
                    . $resultado['linhas_afetadas'] . ' linha(s).')
                : $rota->with('warning', 'Simulação concluída — nada foi gravado. A gravação real esbarraria em: '
                    . implode(' ', $resultado['impedimentos']));
        }

        if ($resultado['linhas_afetadas'] === 0) {
            return $rota->with(
                'warning',
                "A gravação foi enviada ao Questor mas não alterou nenhuma linha — a ordem #{$ordem} "
                . 'já havia saído da fila. Nada mudou; confira o estado atual acima.'
            );
        }

        return $rota->with(
            'success',
            "Ordem #{$ordem} {$verbo} no Questor."
        );
    }
}
