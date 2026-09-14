<?php

namespace App\Http\Controllers;

use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Models\Weekday;
use App\Services\HomeAssistant\ContactorState;
use App\Services\HomeAssistant\ContactorStateResolver;
use App\Services\HomeAssistant\ManualCommandService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeAssistantController extends Controller
{
    public function __construct(private ManualCommandService $commands)
    {
    }

    public function index(ContactorStateResolver $resolver)
    {
        $now = Carbon::now();
        $contactors = $resolver->contactors();

        // Uma consulta só: as reservas do dia servem ao estado de agora e à linha do tempo.
        $daySchedules = $resolver->schedulesBetween($now->copy()->startOfDay(), $now->copy()->endOfDay());

        $states = $contactors->mapWithKeys(fn ($c) => [$c->id => $resolver->resolve($c, $now, $daySchedules)]);
        $timelines = $contactors->mapWithKeys(fn ($c) => [$c->id => $resolver->timeline($c, $now, $daySchedules)]);

        // Agendamentos (não ações rápidas) decidindo o estado de algum contator neste instante
        $inEffectIds = $states
            ->filter(fn ($state) => $state->source === ContactorState::SOURCE_OVERRIDE)
            ->map(fn ($state) => $state->override->id)
            ->unique()->values()->all();

        // Ações rápidas aparecem no cartão do contator, não na lista de agendamentos
        $overrides = HomeAssistantOverride::with(['contactors', 'weekdays', 'windows', 'creator'])
            ->where('is_quick', false)
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        // Ativos (vigentes) x arquivados (pausados ou expirados)
        [$activeOverrides, $archivedOverrides] = $overrides->partition(
            fn ($o) => $o->is_active && ! $o->is_expired
        );

        $weekdays = Weekday::orderBy('id')->get();

        return view('home-assistant.index', compact(
            'now', 'contactors', 'states', 'timelines', 'inEffectIds',
            'activeOverrides', 'archivedOverrides', 'weekdays'
        ));
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

    /**
     * Liga/desliga um contator imediatamente até o fim do dia.
     *
     * Sem duração: o botão do painel vale até a meia-noite. Comando com prazo
     * é coisa da API (ver HomeAssistantApiController).
     */
    public function quickAction(Request $request, Contactor $contactor)
    {
        $request->validate(['state' => 'required|in:on,off']);

        $this->commands->apply(
            contactor: $contactor,
            state: $request->state,
            minutes: null,
            origin: 'Painel',
            userId: auth()->id(),
        );

        $msg = $request->state === 'on' ? 'Contator ligado até o fim do dia.' : 'Contator desligado até o fim do dia.';
        return redirect()->back()->with('success', $msg);
    }

    /** Remove a ação rápida do contator, voltando ao agendamento/padrão. */
    public function clearQuick(Contactor $contactor)
    {
        $this->commands->clear($contactor);
        return redirect()->back()->with('success', 'Contator de volta ao automático.');
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

        return redirect()->to(route('home-assistant.index') . '#schedules')->with('success', 'Agendamento criado com sucesso!');
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

        return redirect()->to(route('home-assistant.index') . '#schedules')->with('success', 'Agendamento atualizado!');
    }

    public function toggleOverride(HomeAssistantOverride $override)
    {
        $override->update(['is_active' => ! $override->is_active]);
        $estado = $override->is_active ? 'ativado' : 'pausado';
        return redirect()->to(route('home-assistant.index') . '#schedules')->with('success', "Agendamento {$estado}.");
    }

    public function destroyOverride(HomeAssistantOverride $override)
    {
        $override->delete();
        return redirect()->to(route('home-assistant.index') . '#schedules')->with('success', 'Agendamento removido!');
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
            'windows'              => 'nullable|array|required_if:mode,schedule_override',
            'windows.*.turn_on_at' => 'required_with:windows|date_format:H:i',
            'windows.*.turn_off_at'=> 'required_with:windows|date_format:H:i|different:windows.*.turn_on_at',
            'windows.*.state'      => 'nullable|in:on,off',
        ], [
            'contactors.required'          => 'Selecione ao menos um contator.',
            'windows.required_if'          => 'Adicione ao menos uma faixa de horário.',
            'windows.*.turn_off_at.different' => 'O horário final de uma faixa precisa ser diferente do inicial.',
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
