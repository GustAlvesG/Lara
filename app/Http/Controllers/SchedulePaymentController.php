<?php

namespace App\Http\Controllers;

use App\Models\SchedulePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchedulePaymentRequest;
use App\Http\Requests\UpdateSchedulePaymentRequest;
use App\Models\Schedule;
//use DB
use Illuminate\Support\Facades\DB;

class SchedulePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSchedulePaymentRequest $request): \Illuminate\Http\JsonResponse
    {
        // 1. Obtém dados validados e os IDs dos agendamentos
        $validated = $request->all(); 
        $scheduleIds = $validated['schedule_ids'] ?? [];

        // Verifica a colisão, usando o novo método auxiliar
        // Mantendo o if(2 == 2) como "feature flag" conforme o código original.
        if (2 == 2) {
            $collidingSchedules = $this->checkSchedulesForCollisions($scheduleIds);

            if ($collidingSchedules->isNotEmpty()) {
                // Agrupa os resultados da colisão para retorno
                $otherSchedulesGrouped = $collidingSchedules->groupBy('place_id');
                
                // Retorna os agendamentos duplicados/outros
                return response()->json([
                    'message' => 'Other schedules found with same place_id and start_schedule.',
                    'data' => $otherSchedulesGrouped
                ], 409); // 409 Conflict
            }
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
            ->where('status_id', '!=', 0) // Ignora cancelados
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
                        ->where('status_id', '!=', 0) // Ignora cancelados
            
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
