<?php

namespace App\Http\Controllers\Questor;

use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Services\Questor\QuestorAuthorizationWriter;
use App\Services\Questor\QuestorGate;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Autorização de Ordem de Compra — a fila que vem do Questor.
 *
 * **Esta versão não altera nada no ERP.** Ela lista as ordens pendentes, abre o
 * detalhe com os itens e, no lugar de aprovar, mostra a *simulação*: o `UPDATE`
 * exato que seria enviado, o antes/depois dos campos e quantas linhas ele
 * pegaria agora. É o que permite conferir a integração contra o banco real de
 * produção sem escrever nele.
 *
 * O fluxo de aprovação da própria Lara (níveis, alçadas, quem aprovou o quê)
 * ainda não existe: ele é a próxima versão, e é ele que vai guardar o histórico
 * — no Questor só cabe um autorizador, que será o usuário técnico.
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
            ]);
        } catch (QuestorException $e) {
            return redirect()->route('questor.purchase-orders.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Simula a autorização. Nada é gravado — a prévia volta pela sessão e é
     * exibida no detalhe da ordem.
     */
    public function simulateApproval(int $ordem)
    {
        return $this->simulate(fn() => $this->writer->approve($ordem), $ordem);
    }

    /**
     * Simula a reprovação, com o motivo que iria para `DS_MOTIVO_REPROVADO`.
     */
    public function simulateRejection(Request $request, int $ordem)
    {
        $dados = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ], [], ['motivo' => 'motivo da reprovação']);

        return $this->simulate(fn() => $this->writer->reject($ordem, $dados['motivo']), $ordem);
    }

    /**
     * O caminho comum das duas simulações: roda, devolve a prévia pela sessão e
     * avisa na cor certa — verde quando passaria limpo, amarelo quando há
     * impedimento. Nunca "sucesso" sem ressalva: a operação não aconteceu.
     */
    private function simulate(callable $acao, int $ordem)
    {
        try {
            $previa = $acao();
        } catch (QuestorException $e) {
            return redirect()
                ->route('questor.purchase-orders.show', $ordem)
                ->with('error', $e->getMessage());
        }

        $rota = redirect()
            ->route('questor.purchase-orders.show', $ordem)
            ->with('questor_simulacao', $previa);

        if ($previa['impedimentos'] !== []) {
            return $rota->with(
                'warning',
                'Simulação concluída — nada foi gravado. A gravação real esbarraria em: '
                . implode(' ', $previa['impedimentos'])
            );
        }

        return $rota->with(
            'success',
            'Simulação concluída — nada foi gravado no Questor. A instrução pegaria '
            . $previa['linhas_afetadas'] . ' linha(s).'
        );
    }
}
