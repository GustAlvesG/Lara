<?php

namespace App\Http\Controllers\Waitlist;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaitlistClassRequest;
use App\Models\Waitlist\WaitlistClass;
use App\Models\Waitlist\WaitlistEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * InfoClube › Fila de Espera: as turmas (cadastro simples no Lara) e a fila
 * de cada uma. Permissão `infoclube.fila-espera`, no corte da rota.
 *
 * Só não sócios. A fila dos sócios fica no MultiClubes, sem vínculo com esta.
 */
class WaitlistClassController extends Controller
{
    /** Filtro de situação da lista de turmas: valor => rótulo. */
    public const SITUATIONS = [
        '' => 'Turmas ativas',
        'inativas' => 'Turmas inativas',
        'todas' => 'Todas as turmas',
    ];

    public function index(Request $request)
    {
        $filters = $request->only(['q', 'situacao']);

        $query = WaitlistClass::query()
            ->withCount(['entries as waiting_count' => fn (Builder $q) => $q->waiting()])
            ->withMin(['entries as oldest_joined_at' => fn (Builder $q) => $q->waiting()], 'joined_at')
            ->ordered();

        if (filled($filters['q'] ?? null)) {
            $term = trim($filters['q']);
            $query->where(fn (Builder $q) => $q->where('modality', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }

        match ($filters['situacao'] ?? '') {
            'inativas' => $query->where('active', false),
            'todas' => null,
            default => $query->active(),
        };

        return view('waitlist.index', [
            'classes' => $query->paginate(30)->withQueryString(),
            'filters' => $filters,
            'situations' => self::SITUATIONS,
            'waitingPeople' => WaitlistEntry::waiting()->distinct()->count('waitlist_person_id'),
        ]);
    }

    public function create()
    {
        return view('waitlist.classes.form', [
            'waitlistClass' => null,
            'modalities' => $this->modalities(),
        ]);
    }

    public function store(WaitlistClassRequest $request)
    {
        $class = WaitlistClass::create($request->validated() + ['created_by' => Auth::id()]);

        return redirect()->route('waitlist.classes.show', $class)
            ->with('success', "Turma {$class->label()} cadastrada.");
    }

    public function show(Request $request, WaitlistClass $waitlistClass)
    {
        $closed = $request->query('situacao') === 'encerrados';

        $entries = $closed
            ? $waitlistClass->entries()->closed()->with(['person', 'closer'])
                ->orderByDesc('closed_at')->paginate(30)->withQueryString()
            : $waitlistClass->entries()->waiting()->with(['person', 'latestContact.user'])
                ->inQueueOrder()->get();

        return view('waitlist.classes.show', [
            'waitlistClass' => $waitlistClass,
            'entries' => $entries,
            'closed' => $closed,
            'waitingCount' => $closed ? $waitlistClass->waitingEntries()->count() : $entries->count(),
        ]);
    }

    public function edit(WaitlistClass $waitlistClass)
    {
        return view('waitlist.classes.form', [
            'waitlistClass' => $waitlistClass,
            'modalities' => $this->modalities(),
        ]);
    }

    public function update(WaitlistClassRequest $request, WaitlistClass $waitlistClass)
    {
        $waitlistClass->update($request->validated());

        return redirect()->route('waitlist.classes.show', $waitlistClass)
            ->with('success', "Turma {$waitlistClass->label()} atualizada.");
    }

    /**
     * Excluir só turma que nunca teve ninguém: a que teve fila guarda o
     * histórico de quem passou por ela — essa se desativa.
     */
    public function destroy(WaitlistClass $waitlistClass)
    {
        if ($waitlistClass->entries()->exists()) {
            return redirect()->route('waitlist.classes.edit', $waitlistClass)
                ->with('warning', 'Esta turma já teve gente na fila e guarda esse histórico. Desmarque "Turma ativa" para ela não receber mais ninguém.');
        }

        $label = $waitlistClass->label();
        $waitlistClass->delete();

        return redirect()->route('waitlist.index')->with('success', "Turma {$label} excluída.");
    }

    /** Modalidades já usadas, para sugerir no campo e evitar "Natação" × "natacao". */
    private function modalities(): array
    {
        return WaitlistClass::query()->distinct()->orderBy('modality')->pluck('modality')->all();
    }
}
