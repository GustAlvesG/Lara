<?php

namespace App\Http\Controllers;

use App\Models\ParkingAuthorization;
use App\Services\ParkingAuthorizationService;
use App\Http\Requests\CheckParkingPlateRequest;
use App\Http\Requests\StoreParkingAuthorizationRequest;
use App\Http\Requests\UpdateParkingAuthorizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ParkingAuthorizationController extends Controller
{
    /** Colunas liberadas para ordenação, para não aceitar SQL vindo da query string. */
    private const SORTABLE = ['plate', 'name', 'expiration_date'];

    public function __construct(
        protected ParkingAuthorizationService $service
    ) {}

    public function index(Request $request): View
    {
        $search    = trim((string) $request->query('q', ''));
        $sort      = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'plate';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $authorizations = ParkingAuthorization::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($group) => $this->applySearch($group, $search)
            ))
            ->orderBy($sort, $direction)
            ->when($sort !== 'plate', fn ($query) => $query->orderBy('plate'))
            ->paginate(20)
            ->withQueryString();

        return view('parking.authorizations.index', compact('authorizations', 'search', 'sort', 'direction'));
    }

    /**
     * Busca por nome ou placa. A placa também é comparada na forma normalizada
     * para que "ABC-1234" encontre registros gravados como "ABC1234" e vice-versa.
     */
    private function applySearch($query, string $search): void
    {
        $query->where('name', 'like', "%{$search}%")
            ->orWhere('plate', 'like', "%{$search}%");

        $normalized = $this->service->normalizePlate($search);

        if ($normalized !== '') {
            $query->orWhereRaw(
                "UPPER(REPLACE(REPLACE(REPLACE(plate, '-', ''), ' ', ''), '.', '')) LIKE ?",
                ["%{$normalized}%"]
            );
        }
    }

    public function create(): View
    {
        return view('parking.authorizations.create');
    }

    public function store(StoreParkingAuthorizationRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());
        return redirect()->route('parking-authorizations.index')
            ->with('success', 'Placa cadastrada com sucesso.');
    }

    public function edit(ParkingAuthorization $parkingAuthorization): View
    {
        return view('parking.authorizations.edit', ['authorization' => $parkingAuthorization]);
    }

    public function update(UpdateParkingAuthorizationRequest $request, ParkingAuthorization $parkingAuthorization): RedirectResponse
    {
        $this->service->update($request->validated(), $parkingAuthorization);
        return redirect()->route('parking-authorizations.index')
            ->with('success', 'Placa atualizada com sucesso.');
    }

    public function destroy(ParkingAuthorization $parkingAuthorization): RedirectResponse
    {
        $parkingAuthorization->delete();
        return redirect()->route('parking-authorizations.index')
            ->with('success', 'Placa removida com sucesso.');
    }

    public function checkPlate(string $plate): JsonResponse
    {
        return response()->json($this->service->checkPlate($plate));
    }

    /**
     * Consulta feita pelo LPR da câmera. O status code decide se o portão abre:
     * 200 responde valid true/false, 4xx é negação definitiva e 5xx faz o
     * cliente cair para a lista local. Por isso qualquer falha de servidor
     * precisa sair como 5xx, e nunca como 4xx ou 200.
     */
    public function checkPlateFromCamera(CheckParkingPlateRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->service->checkPlate($data['plate']);
        } catch (\Throwable $e) {
            Log::error('Falha ao verificar placa do LPR', [
                'plate'     => $data['plate'] ?? null,
                'camera'    => $data['camera'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'service_unavailable',
                'message' => 'Falha ao consultar as autorizações.',
            ], 503);
        }

        $this->service->logAccessAttempt($data, $result);

        return response()->json($result);
    }

    public function listAuthorized(): JsonResponse
    {
        $authorizations = $this->service->getValidAuthorizations();

        return response()->json([
            'checked_at' => now()->toDateTimeString(),
            'total'      => $authorizations->count(),
            'plates'     => $authorizations->map(fn (ParkingAuthorization $item) => [
                'plate'           => $item->plate,
                'name'            => $item->name,
                'expiration_date' => $item->expiration_date->toDateString(),
            ]),
        ]);
    }
}
