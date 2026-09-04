<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Models\Fleet\FleetTrip;
use App\Services\FleetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de quilometragem da frota, consumida pelo sistema da portaria.
 *
 * A saída e o retorno são duas chamadas do mesmo ciclo, e o cliente não
 * precisa guardar o id da viagem entre uma e outra: o retorno é resolvido pelo
 * veículo. Toda recusa volta com um `error` estável — é por ele que a portaria
 * decide entre repetir, confirmar com `force` ou avisar o operador.
 */
class FleetApiController extends Controller
{
    public function __construct(private FleetService $fleet)
    {
    }

    /** Situação da frota: quem está na garagem, quem está na rua e com quem. */
    public function vehicles(Request $request): JsonResponse
    {
        $vehicles = $this->fleet->vehiclesWithStatus($request->boolean('all'));

        return response()->json([
            'ok'       => true,
            'vehicles' => $vehicles->map(fn ($vehicle) => $this->fleet->vehiclePayload($vehicle))->values(),
        ]);
    }

    /** Só as viagens aguardando baixa de retorno. */
    public function openTrips(): JsonResponse
    {
        return response()->json([
            'ok'    => true,
            'trips' => $this->fleet->openTrips()->map->toApiArray()->values(),
        ]);
    }

    public function departure(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle'       => 'required_without:vehicle_id|string',
            'vehicle_id'    => 'nullable|integer',
            'driver'        => 'required_without_all:driver_name,employee_id|string',
            'driver_name'   => 'nullable|string|max:255',
            'employee_id'   => 'nullable|integer',
            'destination'   => 'required|string|max:255',
            'odometer'      => 'required',
            'obs'           => 'nullable|string',
            'operator'      => 'nullable|string|max:255',
            'registered_at' => 'nullable|date',
            'force'         => 'nullable|boolean',
        ]);

        return $this->respond($this->fleet->registerDeparture($request->all()), 201);
    }

    /**
     * Retorno da viagem aberta. `vehicle` basta; `trip_id` existe para o caso
     * de a portaria querer fechar uma viagem específica que já tem em mãos.
     */
    public function returnTrip(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle'       => 'required_without_all:vehicle_id,trip_id|string',
            'vehicle_id'    => 'nullable|integer',
            'trip_id'       => 'nullable|integer',
            'odometer'      => 'required',
            'obs'           => 'nullable|string',
            'operator'      => 'nullable|string|max:255',
            'registered_at' => 'nullable|date',
            'force'         => 'nullable|boolean',
        ]);

        return $this->respond($this->fleet->registerReturn($request->all()));
    }

    /** Histórico paginado, com os mesmos filtros da tela. */
    public function trips(Request $request): JsonResponse
    {
        $trips = $this->fleet->tripsQuery($request->all())
            ->paginate(min((int) $request->input('per_page', 25), 100))
            ->withQueryString();

        return response()->json([
            'ok'    => true,
            'trips' => collect($trips->items())->map->toApiArray()->values(),
            'meta'  => [
                'current_page' => $trips->currentPage(),
                'last_page'    => $trips->lastPage(),
                'per_page'     => $trips->perPage(),
                'total'        => $trips->total(),
            ],
        ]);
    }

    /** Autocomplete de motorista por nome, matrícula ou CPF. */
    public function drivers(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2']);

        return response()->json([
            'ok'      => true,
            'drivers' => $this->fleet->searchDrivers($request->string('q')->value()),
        ]);
    }

    /** Saída registrada por engano: cancela e libera o veículo. */
    public function cancel(Request $request, FleetTrip $trip): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        return $this->respond($this->fleet->cancelTrip($trip, $request->input('reason')));
    }

    /**
     * O status HTTP sai do código de erro do serviço, para que a portaria
     * possa tratar 409 (conflito de estado) e 422 (número a conferir) sem ler
     * o corpo da resposta.
     */
    private function respond(array $result, int $successStatus = 200): JsonResponse
    {
        if ($result['ok']) {
            return response()->json($result, $successStatus);
        }

        return response()->json($result, FleetService::ERROR_STATUS[$result['error']] ?? 422);
    }
}
