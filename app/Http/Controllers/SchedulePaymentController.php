<?php

namespace App\Http\Controllers;

use App\Models\SchedulePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchedulePaymentRequest;
use App\Http\Requests\UpdateSchedulePaymentRequest;
use App\Models\Schedule;
use App\Models\Member;
//use DB
use Illuminate\Support\Facades\DB;

class SchedulePaymentController extends Controller
{

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
     * Display the specified resource.
     */
    public function show(SchedulePayment $schedulePayment)
    {
        //
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
