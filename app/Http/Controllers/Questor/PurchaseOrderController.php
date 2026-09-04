<?php

namespace App\Http\Controllers\Questor;

use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderApproval;
use App\Models\QuestorOrderDecision;
use App\Models\User;
use App\Services\Questor\PurchaseOrderApprovalService;
use App\Services\Questor\QuestorGate;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Autorização de Ordem de Compra — a fila que vem do Questor.
 *
 * Lista as ordens pendentes, abre o detalhe com os itens e conduz o fluxo de
 * três níveis: Contabilidade → Gerência → Diretoria. O Questor só é tocado
 * quando o terceiro fecha; até lá, todo o estado é da Lara.
 *
 * Os níveis 1 e 2 são decididos aqui, no painel interno. O nível 3 é da
 * Diretoria, que decide pelo site externo (ver a API em `routes/api.php`) —
 * mas um diretor que também tem acesso ao painel decide por aqui igual, pela
 * mesma rota. Quem pode o quê é o serviço quem sabe.
 *
 * Erros de integração viram mensagem na tela em vez de 500: quem abre isto está
 * tentando destravar uma compra, e "o Questor não respondeu" é uma resposta
 * melhor do que uma página de erro.
 */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly QuestorPurchaseOrders $orders,
        private readonly PurchaseOrderApprovalService $flow,
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
            'processos' => collect(),
            'erro' => null,
        ];

        try {
            $dados['ordens'] = $this->orders->pending($filtros);
            $dados['resumo'] = $this->orders->pendingSummary();
            $dados['filiais'] = $this->orders->pendingBranches();
            $dados['usuarioTecnico'] = $this->orders->technicalUser();

            // Em que nível está cada ordem da página — numa consulta só. Sem
            // isto, a coluna de estado faria uma consulta por linha.
            $dados['processos'] = PurchaseOrderApproval::open()
                ->whereIn('cd_ordem_compra', $dados['ordens']->pluck('CD_ORDEM_COMPRA')->all())
                ->get()
                ->keyBy('cd_ordem_compra');
        } catch (QuestorException $e) {
            $dados['erro'] = $e->getMessage();
        }

        return view('questor.purchase-orders.index', $dados);
    }

    /**
     * Detalhe da ordem: cabeçalho, fornecedor, itens e o painel de decisão.
     */
    public function show(Request $request, int $ordem)
    {
        try {
            $processo = $this->flow->current($ordem);

            return view('questor.purchase-orders.show', [
                'config' => QuestorGate::summary(),
                'ordem' => $this->orders->find($ordem),
                'itens' => $this->orders->items($ordem),
                'usuarioTecnico' => $this->orders->technicalUser(),
                'processo' => $processo,
                // O passo que ESTE usuário pode decidir agora — é o que decide
                // se a tela mostra botões ou só o andamento.
                'meuPasso' => $processo?->stepFor($request->user()),
                // Só carregado quando é a vez da Gerência: é a tela dela que
                // escolhe a Diretoria.
                'diretores' => $processo?->current_level === PurchaseOrderApproval::LEVEL_MANAGEMENT
                    ? User::directors()->get()
                    : collect(),
                'sugeridos' => $processo?->current_level === PurchaseOrderApproval::LEVEL_MANAGEMENT
                    ? $this->flow->suggestedDirectors($ordem)->pluck('id')->all()
                    : [],
                'ehContabilidade' => $request->user()->isAccountingCoordinator(),
                'decisoes' => QuestorOrderDecision::where('cd_ordem_compra', $ordem)
                    ->orderByDesc('id')
                    ->get(),
            ]);
        } catch (QuestorException $e) {
            return redirect()->route('questor.purchase-orders.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Decide o nível em que a ordem está.
     *
     * Uma rota só para os três níveis: quem pode decidir o quê é o serviço que
     * sabe, a partir do processo e dos cargos do usuário. Uma rota por nível
     * obrigaria a tela a adivinhar o estado antes de montar o formulário — e a
     * errar quando outra pessoa decidisse no meio.
     */
    public function decideLevel(Request $request, int $ordem)
    {
        $dados = $request->validate([
            'decisao' => ['required', 'in:aprovar,reprovar'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'diretores' => ['array'],
            'diretores.*' => ['integer'],
        ], [], ['decisao' => 'decisão']);

        $aprovar = $dados['decisao'] === 'aprovar';
        $user = $request->user();
        $nota = $dados['observacao'] ?? null;
        $ip = $request->ip();

        try {
            $processo = $this->flow->current($ordem);

            $processo = match (true) {
                $processo === null => $this->flow->decideAccounting($ordem, $user, $aprovar, $nota, $ip),
                $processo->current_level === PurchaseOrderApproval::LEVEL_MANAGEMENT
                    => $this->flow->decideManagement($ordem, $user, $aprovar, $dados['diretores'] ?? [], $nota, $ip),
                default => $this->flow->decideDirector($ordem, $user, $aprovar, $nota, $ip),
            };
        } catch (QuestorException $e) {
            return redirect()
                ->route('questor.purchase-orders.show', $ordem)
                ->with('error', $e->getMessage());
        }

        [$tipo, $mensagem] = $this->outcome($processo, $ordem);

        return redirect()->route('questor.purchase-orders.show', $ordem)->with($tipo, $mensagem);
    }

    /**
     * A mensagem de retorno.
     *
     * Aprovar um nível intermediário não é "ordem aprovada", e confundir os
     * dois é o que faz alguém achar que a compra saiu quando ela ainda espera a
     * Diretoria. Por isso cada estado tem sua frase.
     *
     * @return array{0: string, 1: string}
     */
    private function outcome(PurchaseOrderApproval $processo, int $ordem): array
    {
        if ($processo->isRejected()) {
            return ['warning', "Ordem #{$ordem} reprovada. O processo foi encerrado."];
        }

        if ($processo->isApproved()) {
            return ['success', "Ordem #{$ordem} aprovada em todos os níveis e autorizada no Questor."];
        }

        $faltam = $processo->pendingSteps()->count();

        if ($processo->current_level === PurchaseOrderApproval::LEVEL_DIRECTORS && $faltam > 0) {
            return ['success', "Aprovação registrada. A ordem aguarda {$faltam} diretor(es)."];
        }

        return ['success', "Aprovação registrada. A ordem está agora em {$processo->currentLevelLabel()}."];
    }
}
