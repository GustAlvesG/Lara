<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuestorException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ApprovalToken;
use App\Models\PurchaseOrderApproval;
use App\Models\PurchaseOrderApprovalStep;
use App\Models\User;
use App\Providers\Services\JwtService;
use App\Services\Questor\PurchaseOrderApprovalService;
use App\Services\Questor\QuestorPurchaseOrders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * API de aprovação para o site externo (Next.js em DMZ).
 *
 * A Lara continua sendo a autoridade: o Next renderiza e repassa, mas quem
 * confere senha, cargo, vez e estado é este controller. Se a decisão pudesse
 * ser tomada do lado de fora, quem alcançasse a API aprovaria como qualquer um.
 *
 * O login é por **matrícula + senha de aprovação** — campo próprio, não a senha
 * do painel (ver `users.approval_password`). Só entra quem está no setor
 * Diretoria e está ativo.
 *
 * Nada aqui devolve dado de outra ordem que não seja a da fila do próprio
 * diretor: a fila é montada a partir dos passos pendentes dele, não da fila
 * geral de compras.
 */
class PurchaseApprovalController extends Controller
{
    /** Validade do token, em horas. Curta: a fila é consultada, não habitada. */
    private const TOKEN_HOURS = 8;

    public function __construct(
        private readonly PurchaseOrderApprovalService $flow,
        private readonly QuestorPurchaseOrders $orders,
    ) {
    }

    /**
     * POST /api/aprovacao/login — matrícula + senha de aprovação → token.
     */
    public function login(Request $request, JwtService $jwt)
    {
        $dados = $request->validate([
            'matricula' => ['required', 'string'],
            'senha' => ['required', 'string'],
        ]);

        $user = User::where('matricula', $dados['matricula'])->first();

        // Uma resposta só para "não existe", "inativo" e "senha errada": separar
        // as três diria a quem está tentando qual matrícula existe.
        $recusa = response()->json(['error' => 'Matrícula ou senha inválida.'], 401);

        if (!$user || (int) $user->status_id !== 1 || !$user->checkApprovalPassword($dados['senha'])) {
            Log::warning('Aprovação de compras: login recusado', [
                'matricula' => $dados['matricula'],
                'ip' => $request->ip(),
            ]);

            return $recusa;
        }

        if (!$user->isDirector()) {
            return response()->json(['error' => 'Usuário sem acesso à aprovação de compras.'], 403);
        }

        $expira = now()->addHours(self::TOKEN_HOURS);

        return response()->json([
            'token' => $jwt->generateToken([
                'uid' => $user->id,
                'scope' => ApprovalToken::SCOPE,
                'iat' => now()->timestamp,
                'exp' => $expira->timestamp,
            ]),
            'expira_em' => $expira->toIso8601String(),
            'usuario' => [
                'id' => $user->id,
                'nome' => $user->name,
                'matricula' => $user->matricula,
            ],
        ]);
    }

    /**
     * GET /api/aprovacao/ordens — a fila do diretor autenticado.
     *
     * Só as ordens em que ele tem passo pendente. O dado do Questor (fornecedor,
     * valor) vem por ordem, então a lista é limitada — é fila de trabalho, não
     * relatório.
     */
    public function index(Request $request)
    {
        $passos = PurchaseOrderApprovalStep::query()
            ->where('user_id', $request->user()->id)
            ->where('decision', PurchaseOrderApprovalStep::PENDING)
            ->whereHas('approval', fn($q) => $q
                ->where('status', PurchaseOrderApproval::STATUS_OPEN)
                ->where('current_level', PurchaseOrderApproval::LEVEL_DIRECTORS))
            ->with('approval')
            ->limit(100)
            ->get();

        return response()->json([
            'ordens' => $passos->map(fn(PurchaseOrderApprovalStep $passo) => [
                'cd_ordem_compra' => $passo->approval->cd_ordem_compra,
                'processo_id' => $passo->approval->id,
                'vl_total' => (float) $passo->approval->vl_total,
                'nr_itens' => $passo->approval->nr_itens,
                'centros_custo' => $passo->approval->cost_centers,
                'sem_centro_custo' => $passo->approval->sem_centro_custo,
                'aguardando_desde' => $passo->approval->updated_at?->toIso8601String(),
                'escolhido_por' => $passo->source === PurchaseOrderApprovalStep::SOURCE_MANUAL
                    ? 'gerente'
                    : 'centro de custo',
            ])->values(),
        ]);
    }

    /**
     * GET /api/aprovacao/ordens/{ordem} — detalhe com itens.
     *
     * Exige passo pendente do próprio diretor: sem isso, a API viraria consulta
     * livre à carteira de compras para quem tem qualquer token válido.
     */
    public function show(Request $request, int $ordem)
    {
        $processo = $this->flow->current($ordem);

        if ($processo === null || $processo->stepFor($request->user()) === null) {
            return response()->json(['error' => 'Ordem não encontrada na sua fila de aprovação.'], 404);
        }

        try {
            $cabecalho = $this->orders->find($ordem);
            $itens = $this->orders->items($ordem);
        } catch (QuestorException $e) {
            return response()->json(['error' => $e->getMessage()], 503);
        }

        return response()->json([
            'cd_ordem_compra' => (int) $cabecalho->CD_ORDEM_COMPRA,
            'processo_id' => $processo->id,
            'filial' => $cabecalho->CD_FILIAL,
            'fornecedor' => [
                'razao_social' => $cabecalho->FORNECEDOR_RAZAO_SOCIAL,
                'fantasia' => $cabecalho->FORNECEDOR_FANTASIA,
                'cnpj' => $cabecalho->FORNECEDOR_CNPJ,
            ],
            'departamento' => $cabecalho->DS_DEPARTAMENTO,
            'solicitante' => $cabecalho->DS_SOLICITANTE,
            'referente' => $cabecalho->DS_REFERENTE,
            'vl_total' => (float) $cabecalho->VL_TOTAL,
            'nr_itens' => (int) $cabecalho->NR_ITENS,
            'dt_cadastro' => $cabecalho->DT_CADASTRO,
            'itens' => $itens->map(fn($i) => [
                'item' => (int) $i->CD_ITEM,
                'material' => $i->DS_MATERIAL,
                'unidade' => $i->DS_UNIDADE,
                'quantidade' => (float) $i->NR_QUANTIDADE,
                'vl_unitario' => (float) $i->VL_UNITARIO,
                'vl_total' => (float) $i->VL_TOTAL,
                'centro_custo' => $i->CD_CENTRO_CUSTO,
            ])->values(),
            'aprovacao' => $this->approvalPayload($processo),
        ]);
    }

    /**
     * POST /api/aprovacao/ordens/{ordem}/decidir
     *
     * Aprovar sendo o último diretor pendente é o que dispara a gravação no
     * Questor — por isso a resposta diz explicitamente se a ordem foi ao ERP.
     */
    public function decide(Request $request, int $ordem)
    {
        $dados = $request->validate([
            'decisao' => ['required', 'in:aprovar,reprovar'],
            'observacao' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $processo = $this->flow->decideDirector(
                $ordem,
                $request->user(),
                $dados['decisao'] === 'aprovar',
                $dados['observacao'] ?? null,
                $request->ip(),
            );
        } catch (QuestorException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'resultado' => $processo->status,
            'gravado_no_questor' => $processo->isApproved() && $processo->questor_decision_id !== null,
            'aprovacao' => $this->approvalPayload($processo),
        ]);
    }

    /** @return array<string, mixed> */
    private function approvalPayload(PurchaseOrderApproval $processo): array
    {
        return [
            'id' => $processo->id,
            'status' => $processo->status,
            'nivel_atual' => $processo->current_level,
            'nivel_atual_rotulo' => $processo->currentLevelLabel(),
            'aguardando' => $processo->pendingSteps()->count(),
            'passos' => $processo->steps
                ->sortBy(['level', 'id'])
                ->map(fn(PurchaseOrderApprovalStep $s) => [
                    'nivel' => $s->level,
                    'responsavel' => $s->assigneeLabel(),
                    'decisao' => $s->decision,
                    'decidido_por' => $s->decided_by_name,
                    'decidido_em' => $s->decided_at?->toIso8601String(),
                    'observacao' => $s->note,
                ])->values(),
        ];
    }
}
