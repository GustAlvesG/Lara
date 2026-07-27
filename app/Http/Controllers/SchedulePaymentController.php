<?php

namespace App\Http\Controllers;

use App\Models\SchedulePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchedulePaymentRequest;
use App\Http\Requests\UpdateSchedulePaymentRequest;
use App\Models\Schedule;
use App\Models\Member;
use App\Models\Status;
use App\Services\RedeItauService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
//use DB
use Illuminate\Support\Facades\DB;

class SchedulePaymentController extends Controller
{
    protected RedeItauService $redeItauService;

    public function __construct(RedeItauService $redeItauService)
    {
        $this->redeItauService = $redeItauService;
    }

    /**
     * Lista todos os pagamentos, com filtros para a tela de gestão de pagamentos.
     */
    public function index(Request $request)
    {
        $query = SchedulePayment::with(['status', 'schedules.place.group', 'schedules.member']);

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->input('status_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('member')) {
            $term = $request->input('member');
            $query->whereHas('schedules.member', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('cpf', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('paid_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('paid_at', '<=', $request->input('date_to'));
        }

        $payments = $query->orderByDesc('paid_at')->paginate(25)->withQueryString();
        $statuses = Status::all();

        return view('payments.index', compact('payments', 'statuses'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSchedulePaymentRequest $request): \Illuminate\Http\JsonResponse
    {
        // 1. Obtém dados validados e os IDs dos agendamentos
        $validated = $request->validated();
        $scheduleIds = $validated['schedule_ids'] ?? [];

        // 1.1 Garante que TODOS os agendamentos pertencem ao dono da sessão —
        // nunca confia no schedule_ids do body sem checar posse.
        $sessionUser = $request->input('user');
        $requesterCpf = $sessionUser['username'] ?? null;
        $requesterId = $requesterCpf ? Member::where('cpf', $requesterCpf)->value('id') : null;

        if (!$requesterId) {
            return response()->json(['message' => 'Sessão inválida.'], 401);
        }

        $ownedCount = Schedule::withoutGlobalScopes()
            ->whereIn('id', $scheduleIds)
            ->where('member_id', $requesterId)
            ->count();

        if ($ownedCount !== count(array_unique($scheduleIds))) {
            return response()->json(['message' => 'Um ou mais agendamentos não pertencem ao usuário autenticado.'], 403);
        }

        // 1.2 Idempotência: o mesmo payment_integration_id pode legitimamente
        // aparecer em vários SchedulePayment — quando a pessoa seleciona N
        // horários, são N agendamentos pagos em uma única cobrança (mesmo tid).
        // O que nunca pode acontecer é o MESMO agendamento ser vinculado a um
        // pagamento duas vezes. Se todos os ids pedidos já estiverem pagos com
        // esse mesmo tid, é um reenvio/retry — devolve o pagamento existente em
        // vez de reprocessar. Se a situação for mista (alguns pagos, outros não,
        // ou pagos com um tid diferente), é conflito genuíno.
        $alreadyLinked = Schedule::withoutGlobalScopes()
            ->whereIn('id', $scheduleIds)
            ->whereNotNull('schedule_payment_id')
            ->with('schedulePayment')
            ->get();

        if ($alreadyLinked->isNotEmpty()) {
            $isSameRetry = $alreadyLinked->count() === count(array_unique($scheduleIds))
                && $alreadyLinked->every(function ($schedule) use ($validated) {
                    return $schedule->schedulePayment
                        && $schedule->schedulePayment->payment_integration_id === $validated['payment_integration_id'];
                });

            if ($isSameRetry) {
                return response()->json([
                    'message' => 'Payment already processed.',
                    'data' => $alreadyLinked->first()->schedulePayment,
                ], 200);
            }

            return response()->json(['message' => 'Um ou mais agendamentos já foram pagos.'], 409);
        }

        // Verifica a colisão, usando o novo método auxiliar
        $collidingSchedules = $this->checkSchedulesForCollisions($scheduleIds);
        if ($collidingSchedules->isNotEmpty()) {
            // Agrupa os resultados da colisão para retorno
            $otherSchedulesGrouped = $collidingSchedules->groupBy('place_id');
            
            // Retorna os agendamentos duplicados/outros
            return response()->json([
                'message' => 'Other schedules found with same place_id and start_schedulesadfsdgfsdgfsdfsddfssdfdfssdffsdsdf.',
                'data' => $otherSchedulesGrouped
            ], 409); // 409 Conflict
        }
        

        try {
            // 2. Garante que todas as operações de DB ocorram em uma transação.
            $schedulePayment = DB::transaction(function () use ($validated, $scheduleIds) {
                
                // A. Prepara os dados: remove 'schedule_ids' para a criação do Payment
                $paymentData = array_diff_key($validated, array_flip(['schedule_ids']));
                
                // Converte ISO para timestamp para paid_at
                if (isset($paymentData['paid_at'])) {
                    $paymentData['paid_at'] = date('Y-m-d H:i:s', strtotime((string)$paymentData['paid_at']));
                }
                
                // B. Cria o Schedule Payment (INSERT)
                $schedulePayment = SchedulePayment::create($paymentData);

                // C. Atualiza schedules relacionados em lote, se houver IDs (UPDATE único)
                if (!empty($scheduleIds)) {
                    Schedule::withoutGlobalScopes()->whereIn('id', $scheduleIds)->update([
                        'schedule_payment_id' => $schedulePayment->id,
                        'status_id' => $schedulePayment->status_id
                    ]);
                }
                
                return $schedulePayment;
            });

            // 3. Retorno de sucesso (201 Created)
            return response()->json([
                'message' => 'Schedule Payment created and schedules linked successfully.',
                'data' => $schedulePayment->refresh()
            ], 201);

        } catch (\Exception $e) {
            // 4. Tratamento de erro (o rollback já ocorreu)
            report($e);

            return response()->json([
                'message' => 'Failed to create Schedule Payment due to an internal error. Operation was safely rolled back.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource, com opção de consultar dados ao vivo na Rede.
     */
    public function show(Request $request, SchedulePayment $schedulePayment)
    {
        $schedulePayment->load(['status', 'refunder', 'schedules.place.group', 'schedules.member']);

        $redeSummary = null;
        $redeError = null;

        if ($request->boolean('consultar_rede') && $schedulePayment->payment_integration_id) {
            try {
                $redeTransaction = $this->redeItauService->getTransaction($schedulePayment->payment_integration_id);
                $redeSummary = $this->summarizeRedeTransaction($redeTransaction);
            } catch (RequestException $e) {
                $redeError = 'Não foi possível consultar a transação na Rede: ' . $e->getMessage();
            } catch (\Throwable $e) {
                $redeError = 'Falha inesperada ao consultar a Rede: ' . $e->getMessage();
            }
        }

        return view('payments.show', compact('schedulePayment', 'redeSummary', 'redeError'));
    }

    /**
     * Reduz a resposta completa da Rede aos dados relevantes para exibição na tela
     * (dados de cartão/portador ficam dentro de "authorization" na API da Rede).
     */
    private function summarizeRedeTransaction(array $transaction): array
    {
        $authorization = $transaction['authorization'] ?? $transaction;

        return [
            'cardHolderName' => $authorization['cardHolderName'] ?? null,
            'last4' => $authorization['last4'] ?? null,
            'status' => $authorization['status'] ?? null,
            'nsu' => $authorization['nsu'] ?? null,
            'kind' => $authorization['kind'] ?? null,
            'tid' => $authorization['tid'] ?? null,
        ];
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchedulePayment $schedulePayment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchedulePaymentRequest $request, SchedulePayment $schedulePayment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchedulePayment $schedulePayment)
    {
        //
    }

    /**
     * Estorna (total ou parcialmente) um pagamento específico via Rede.
     */
    public function refund(Request $request, SchedulePayment $schedulePayment)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            $this->redeItauService->refundPayment(
                $schedulePayment,
                $validated['amount'] ?? null,
                $request->user()->id
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (RequestException $e) {
            return redirect()->back()->with('error', 'O estorno não funcionou. Tire um print desta mensagem e envie para a TI. Detalhe: ' . $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Falha inesperada ao estornar o pagamento: ' . $e->getMessage());
        }

        return redirect()->route('payment.show', $schedulePayment->id)->with('success', 'Estorno realizado com sucesso.');
    }

    private function checkSchedulesForCollisions(array $scheduleIds): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($scheduleIds)) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // 1. Otimização: Busca apenas as colunas necessárias para a verificação.
        $schedules = Schedule::
            withoutGlobalScopes()
            ->whereIn('id', $scheduleIds)
            ->whereNotIn('status_id', [0, 4])
            ->get(['id', 'place_id', 'start_schedule']);

        // Se a lista de agendamentos buscados for vazia (ex: IDs inválidos), retorna vazio.
        if ($schedules->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        // 2. Prepara os dados: Agrupa por place_id para obter os horários de início relevantes.
        $schedulesByPlace = $schedules->groupBy('place_id')->map(function ($place) {
            // Usa unique() para evitar duplicação de horários.
            return $place->pluck('start_schedule')->unique();
        });
        
        $originalScheduleIds = $schedules->pluck('id')->all();

        // 3. PESQUISA POR OUTROS AGENDAMENTOS (Collision Check Query)
        $otherSchedulesQuery = Schedule::
         withoutGlobalScopes()
         // Exclui os agendamentos que estão sendo pagos agora.
         ->whereNotIn('id', $originalScheduleIds)
        ->whereNotIn('status_id', [0, 4])
            
            // Cria a cláusula WHERE principal combinando place_id E start_schedule
            ->where(function ($query) use ($schedulesByPlace) {
                // Itera sobre o agrupamento [place_id => [horarios]]
                foreach ($schedulesByPlace as $placeId => $startSchedules) {
                    
                    // Para cada place_id, adiciona uma condição OR complexa para checar a combinação
                    // (place_id = X AND start_schedule IN (Y, Z))
                    $query->orWhere(function ($subQuery) use ($placeId, $startSchedules) {
                        $subQuery->where('place_id', $placeId)
                                 ->whereIn('start_schedule', $startSchedules->all());
                    });
                }
            });

        // Retorna a coleção de agendamentos que colidem
        return $otherSchedulesQuery->get();
    }
}
