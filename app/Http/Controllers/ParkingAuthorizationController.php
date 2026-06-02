<?php

namespace App\Http\Controllers;

use App\Models\ParkingAuthorization;
use App\Services\ParkingAuthorizationService;
use App\Http\Requests\StoreParkingAuthorizationRequest;
use App\Http\Requests\UpdateParkingAuthorizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ParkingAuthorizationController extends Controller
{
    public function __construct(
        protected ParkingAuthorizationService $service
    ) {}

    public function index(): View
    {
        $authorizations = ParkingAuthorization::orderBy('plate')->paginate(20);
        return view('parking.authorizations.index', compact('authorizations'));
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
}
