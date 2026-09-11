<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOneOffAccessRequest;
use App\Models\Company\OneOffAccess;
use App\Services\OneOffAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Telas da liberação pontual. A consulta e o registro na portaria ficam no
 * fluxo de sempre (CompanyService, pelo CPF); aqui só se cria, lista e
 * cancela.
 */
class OneOffAccessController extends Controller
{
    public function __construct(private OneOffAccessService $service)
    {
    }

    public function index(Request $request)
    {
        $date = rescue(fn () => Carbon::parse($request->input('date', today()->toDateString())), today(), false);

        $accesses = OneOffAccess::with(['creator', 'canceler'])
            ->onDate($date)
            ->latest('id')
            ->get();

        $stats = [
            'total'     => $accesses->count(),
            'available' => $accesses->filter(fn (OneOffAccess $a) => $a->isAvailable())->count(),
            'used'      => $accesses->whereNotNull('used_at')->count(),
        ];

        return view('companies.one-off.index', compact('accesses', 'stats', 'date'));
    }

    public function create(Request $request)
    {
        // O monitor manda o CPF que acabou de dar "não encontrado".
        return view('companies.one-off.create', [
            'cpf' => preg_replace('/\D/', '', (string) $request->query('cpf', '')),
        ]);
    }

    public function store(StoreOneOffAccessRequest $request)
    {
        $access = $this->service->create($request->validated(), auth()->id());

        return redirect()->route('company.one-off.index')
            ->with('success', "Liberação pontual criada para {$access->name}: uma entrada, válida só hoje.");
    }

    public function cancel(OneOffAccess $oneOffAccess)
    {
        try {
            $this->service->cancel($oneOffAccess, auth()->id());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', "Liberação de {$oneOffAccess->name} cancelada.");
    }
}
