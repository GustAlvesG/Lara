<?php

namespace App\Http\Controllers;

use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Models\Weekday;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeAssistantController extends Controller
{
    public function index()
    {
        $contactors = Contactor::with([
            'places',
            'overrides' => fn ($q) => $q->with(['weekdays', 'windows']),
        ])->orderBy('name')->get();

        $overrides = HomeAssistantOverride::with(['contactors', 'weekdays', 'windows', 'creator'])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        // Ativos (vigentes) x arquivados (pausados ou expirados)
        [$activeOverrides, $archivedOverrides] = $overrides->partition(
            fn ($o) => $o->is_active && ! $o->is_expired
        );

        $weekdays = Weekday::orderBy('id')->get();

        return view('home-assistant.index', compact('contactors', 'activeOverrides', 'archivedOverrides', 'weekdays'));
    }

    /* ───────────────────────────── Contactors ───────────────────────────── */

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'entity_id' => 'required|string|max:255|unique:contactors,entity_id',
        ]);

        Contactor::create($request->only('name', 'entity_id'));

        return redirect()->route('home-assistant.index')->with('success', 'Contator criado com sucesso!');
    }

    public function update(Request $request, Contactor $contactor)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'entity_id' => 'required|string|max:255|unique:contactors,entity_id,' . $contactor->id,
        ]);

        $contactor->update($request->only('name', 'entity_id'));

        return redirect()->route('home-assistant.index')->with('success', 'Contator atualizado!');
    }

    public function destroy(Contactor $contactor)
    {
        $contactor->delete();
        return redirect()->route('home-assistant.index')->with('success', 'Contator removido!');
    }

    /* ─────────────────────── Ações rápidas (por contator) ─────────────────────── */

    /** Liga/desliga um contator imediatamente até o fim do dia (override de alta prioridade). */
    public function quickAction(Request $request, Contactor $contactor)
    {
        $request->validate(['state' => 'required|in:on,off']);

        // Remove ações rápidas anteriores deste contator
        $this->clearQuickFor($contactor);

        $override = HomeAssistantOverride::create([
            'name'       => $request->state === 'on' ? 'Ligado manualmente' : 'Desligado manualmente',
            'mode'       => $request->state === 'on' ? 'manual_on' : 'manual_off',
            'priority'   => 1000, // ações rápidas têm precedência sobre agendamentos
            'start_date' => Carbon::today(),
            'end_date'   => Carbon::today(),
            'is_active'  => true,
            'is_quick'   => true,
            'created_by' => auth()->id(),
        ]);

        $override->contactors()->attach($contactor->id);

        $msg = $request->state === 'on' ? 'Contator ligado até o fim do dia.' : 'Contator desligado até o fim do dia.';
        return redirect()->back()->with('success', $msg);
    }

    /** Remove a ação rápida do contator, voltando ao agendamento/padrão. */
    public function clearQuick(Contactor $contactor)
    {
        $this->clearQuickFor($contactor);
        return redirect()->back()->with('success', 'Voltando ao agendamento padrão.');
    }

    private function clearQuickFor(Contactor $contactor): void
    {
        $ids = $contactor->overrides()->where('is_quick', true)->pluck('home_assistant_overrides.id');
        if ($ids->isNotEmpty()) {
            HomeAssistantOverride::whereIn('id', $ids)->delete(); // pivots/windows caem por cascade
        }
    }

    /* ─────────────────────────── Agendamentos (CRUD) ─────────────────────────── */

    public function storeOverride(Request $request)
    {
        $data = $this->validateOverride($request);

        DB::transaction(function () use ($data, $request) {
            $override = HomeAssistantOverride::create([
                'name'       => $data['name'],
                'mode'       => $data['mode'],
                'priority'   => $data['priority'] ?? 0,
                'start_date' => $data['start_date'] ?? null,
                'end_date'   => $data['end_date'] ?? null,
                'is_active'  => $request->boolean('is_active', true),
                'is_quick'   => false,
                'created_by' => auth()->id(),
            ]);

            $this->syncRelations($override, $data);
        });

        return redirect()->route('home-assistant.index')->with('success', 'Agendamento criado com sucesso!');
    }

    public function updateOverride(Request $request, HomeAssistantOverride $override)
    {
        $data = $this->validateOverride($request);

        DB::transaction(function () use ($override, $data, $request) {
            $override->update([
                'name'       => $data['name'],
                'mode'       => $data['mode'],
                'priority'   => $data['priority'] ?? 0,
                'start_date' => $data['start_date'] ?? null,
                'end_date'   => $data['end_date'] ?? null,
                'is_active'  => $request->boolean('is_active', true),
            ]);

            $override->windows()->delete();
            $this->syncRelations($override, $data);
        });

        return redirect()->route('home-assistant.index')->with('success', 'Agendamento atualizado!');
    }

    public function toggleOverride(HomeAssistantOverride $override)
    {
        $override->update(['is_active' => ! $override->is_active]);
        $estado = $override->is_active ? 'ativado' : 'pausado';
        return redirect()->route('home-assistant.index')->with('success', "Agendamento {$estado}.");
    }

    public function destroyOverride(HomeAssistantOverride $override)
    {
        $override->delete();
        return redirect()->route('home-assistant.index')->with('success', 'Agendamento removido!');
    }

    /* ───────────────────────────── Helpers ───────────────────────────── */

    private function validateOverride(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:255',
            'mode'          => 'required|in:manual_on,manual_off,schedule_override',
            'priority'      => 'nullable|integer|min:0|max:999',
            'start_date'    => 'nullable|date',
            'end_date'      => 'nullable|date|after_or_equal:start_date',
            'contactors'    => 'required|array|min:1',
            'contactors.*'  => 'exists:contactors,id',
            'weekdays'      => 'nullable|array',
            'weekdays.*'    => 'exists:weekdays,id',
            'windows'              => 'nullable|array',
            'windows.*.turn_on_at' => 'required_with:windows|date_format:H:i',
            'windows.*.turn_off_at'=> 'required_with:windows|date_format:H:i',
            'windows.*.state'      => 'nullable|in:on,off',
        ], [
            'contactors.required' => 'Selecione ao menos um local.',
        ]);
    }

    private function syncRelations(HomeAssistantOverride $override, array $data): void
    {
        $override->contactors()->sync($data['contactors']);
        $override->weekdays()->sync($data['weekdays'] ?? []);

        if ($data['mode'] === 'schedule_override' && ! empty($data['windows'])) {
            foreach ($data['windows'] as $window) {
                $override->windows()->create([
                    'turn_on_at'  => $window['turn_on_at'],
                    'turn_off_at' => $window['turn_off_at'],
                    'state'       => $window['state'] ?? 'on',
                ]);
            }
        }
    }
}
