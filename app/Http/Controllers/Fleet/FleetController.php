<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreFleetVehicleRequest;
use App\Http\Requests\Fleet\UpdateFleetVehicleRequest;
use App\Models\Fleet\FleetTrip;
use App\Models\Fleet\FleetVehicle;
use App\Services\FleetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Telas da frota. O registro daqui passa pelo MESMO serviço da API da
 * portaria: é a saída de emergência quando o sistema da guarita está fora do
 * ar, e não pode ter regra própria — dois caminhos com validações diferentes
 * acabam gerando dois hodômetros diferentes para o mesmo carro.
 */
class FleetController extends Controller
{
    public function __construct(private FleetService $fleet)
    {
    }

    public function index(): View
    {
        return view('fleet.index', [
            'vehicles'  => $this->fleet->vehiclesWithStatus(),
            'openTrips' => $this->fleet->openTrips(),
            'stats'     => $this->fleet->stats(),
            'alertHours'=> (int) config('fleet.open_trip_alert_hours', 12),
        ]);
    }

    public function storeDeparture(Request $request): RedirectResponse
    {
        $request->validate([
            'vehicle_id'  => 'required|integer',
            'driver'      => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'odometer'    => 'required|integer|min:0',
            'obs'         => 'nullable|string',
            'force'       => 'nullable|boolean',
        ]);

        $result = $this->fleet->registerDeparture($request->all());

        return $this->back($result, 'Saída registrada.');
    }

    public function storeReturn(Request $request): RedirectResponse
    {
        $request->validate([
            'trip_id'  => 'required|integer',
            'odometer' => 'required|integer|min:0',
            'obs'      => 'nullable|string',
            'force'    => 'nullable|boolean',
        ]);

        $result = $this->fleet->registerReturn($request->all());

        if ($result['ok']) {
            $distance = $result['trip']['distance_km'];
            $message = $distance === null
                ? 'Retorno registrado.'
                : "Retorno registrado. {$distance} km nesta viagem.";

            return redirect()->back()->with('success', $message);
        }

        return $this->back($result, '');
    }

    public function trips(Request $request): View
    {
        return view('fleet.trips', [
            'trips'    => $this->fleet->tripsQuery($request->all())->paginate(25)->withQueryString(),
            'vehicles' => FleetVehicle::orderBy('name')->get(),
            'statuses' => FleetTrip::STATUS_LABELS,
            'filters'  => $request->only(['vehicle_id', 'status', 'driver', 'date_from', 'date_to', 'only_alerts']),
        ]);
    }

    public function cancelTrip(Request $request, FleetTrip $trip): RedirectResponse
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $result = $this->fleet->cancelTrip($trip, $request->input('reason'));

        return $this->back($result, 'Viagem cancelada.');
    }

    /* ---------------------------------------------------------------------
     | Cadastro de veículos
     |--------------------------------------------------------------------*/

    public function vehicles(): View
    {
        return view('fleet.vehicles.index', [
            'vehicles' => FleetVehicle::withCount('trips')->orderBy('name')->get(),
        ]);
    }

    public function createVehicle(): View
    {
        return view('fleet.vehicles.create');
    }

    public function storeVehicle(StoreFleetVehicleRequest $request): RedirectResponse
    {
        $this->fleet->storeVehicle($request->validated());

        return redirect()->route('fleet.vehicles')->with('success', 'Veículo cadastrado.');
    }

    public function editVehicle(FleetVehicle $vehicle): View
    {
        return view('fleet.vehicles.edit', compact('vehicle'));
    }

    public function updateVehicle(UpdateFleetVehicleRequest $request, FleetVehicle $vehicle): RedirectResponse
    {
        $this->fleet->updateVehicle($request->validated(), $vehicle);

        return redirect()->route('fleet.vehicles')->with('success', 'Veículo atualizado.');
    }

    /**
     * Veículo com viagem registrada não é apagado: o histórico de quilometragem
     * dele deixaria de fazer sentido. Nesse caso a saída é desativar, que já
     * tira o carro da lista da portaria.
     */
    public function destroyVehicle(FleetVehicle $vehicle): RedirectResponse
    {
        if ($vehicle->trips()->exists()) {
            return redirect()->route('fleet.vehicles')
                ->with('error', 'Este veículo tem viagens registradas. Desative-o em vez de excluir.');
        }

        $vehicle->delete();

        return redirect()->route('fleet.vehicles')->with('success', 'Veículo removido.');
    }

    private function back(array $result, string $successMessage): RedirectResponse
    {
        return $result['ok']
            ? redirect()->back()->with('success', $successMessage)
            : redirect()->back()->with('error', $result['message'])->withInput();
    }
}
