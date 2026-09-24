<?php

namespace App\Http\Controllers;

use App\Models\Contactor;
use App\Models\HomeAssistantOverride;
use App\Models\LightingSelfServiceDate;
use App\Models\LightingSelfServiceWindow;
use App\Models\MemberLightingActivation;
use App\Models\Place;
use App\Models\Weekday;
use App\Services\HomeAssistant\ContactorState;
use App\Services\HomeAssistant\ContactorStateResolver;
use App\Services\HomeAssistant\ManualCommandService;
use App\Services\HomeAssistant\SelfServiceLightingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeAssistantController extends Controller
{
    public function __construct(private ManualCommandService $commands)
    {
    }

    public function index(ContactorStateResolver $resolver, SelfServiceLightingService $selfService)
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

        /*
         * Autoatendimento do sócio. Só o que ainda vai acontecer: data especial
         * do ano passado é ruído numa tela que serve para decidir o próximo fim
         * de semana. A remoção fica com quem cadastrou.
         */
        $selfServiceDates = LightingSelfServiceDate::whereDate('date', '>=', $now->toDateString())
            ->orderBy('date')
            ->get();

        $selfServicePlaces = $selfService->eligiblePlaces();
        $selfServiceNext   = $selfService->earliestNextWindow($now);
        $selfServiceActive = MemberLightingActivation::activeAt($now)
            ->with('place')
            ->orderBy('ends_at')
            ->get();

        /*
         * Horários: o padrão do clube e as exceções por quadra, os dois
         * indexados por dia da semana para a tabela do painel não procurar
         * linha a linha.
         */
        $rows = LightingSelfServiceWindow::all();

        $selfServiceWindows      = $rows->whereNull('place_id')->keyBy('weekday');
        $selfServicePlaceWindows = $rows->whereNotNull('place_id')
            ->groupBy('place_id')
            ->map(fn ($place) => $place->keyBy('weekday'));

        // A janela efetiva de hoje, quadra a quadra: é o que responde "por que
        // a quadra 2 não acendeu?" sem ninguém abrir o banco.
        $selfServiceToday = $selfServicePlaces->mapWithKeys(
            fn ($place) => [$place->id => $selfService->windowFor($now->copy()->startOfDay(), $place)]
        );

        return view('home-assistant.index', compact(
            'now', 'contactors', 'states', 'timelines', 'inEffectIds',
            'activeOverrides', 'archivedOverrides', 'weekdays',
            'selfServiceDates', 'selfServiceWindows', 'selfServicePlaceWindows',
            'selfServiceToday', 'selfServiceNext', 'selfServicePlaces', 'selfServiceActive'
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

    /* ──────────────── Autoatendimento: horários por dia ──────────────── */

    /**
     * Horário padrão do clube, um por dia da semana (mais o feriado).
     *
     * Os dois campos vazios num dia querem dizer **fechado**, e a linha é
     * gravada assim mesmo: um "fechado" explícito precisa vencer o valor que
     * ainda está em `config/home_assistant.php`, senão apagar o horário no
     * painel não teria efeito nenhum.
     */
    public function saveSelfServiceWindows(Request $request)
    {
        foreach ($this->validateSelfServiceWindows($request) as $weekday => $range) {
            LightingSelfServiceWindow::updateOrCreate(
                ['place_id' => null, 'weekday' => $weekday],
                ['starts_at' => $range['starts_at'] ?? null, 'ends_at' => $range['ends_at'] ?? null]
            );
        }

        return redirect()->to(route('home-assistant.index') . '#self-service')
            ->with('success', 'Horário padrão atualizado.');
    }

    /**
     * Horário de uma quadra específica.
     *
     * Existe por causa das quadras cobertas: elas escurecem antes e precisam de
     * luz mais cedo que a quadra aberta ao lado, no mesmo dia.
     *
     * Cada dia tem três destinos: `inherit` apaga a linha (a quadra volta a
     * seguir o padrão), `closed` grava a linha sem horário (a quadra fica calada
     * naquele dia, mesmo com o clube aberto) e `custom` grava a faixa própria.
     */
    public function saveSelfServicePlaceWindows(Request $request, Place $place)
    {
        $data = $this->validateSelfServiceWindows($request, withMode: true);

        DB::transaction(function () use ($data, $place) {
            foreach ($data as $weekday => $range) {
                $mode = $range['mode'] ?? 'inherit';

                if ($mode === 'inherit') {
                    LightingSelfServiceWindow::where('place_id', $place->id)
                        ->where('weekday', $weekday)->delete();

                    continue;
                }

                LightingSelfServiceWindow::updateOrCreate(
                    ['place_id' => $place->id, 'weekday' => $weekday],
                    $mode === 'closed'
                        ? ['starts_at' => null, 'ends_at' => null]
                        : ['starts_at' => $range['starts_at'] ?? null, 'ends_at' => $range['ends_at'] ?? null]
                );
            }
        });

        return redirect()->to(route('home-assistant.index') . '#self-service')
            ->with('success', 'Horário de ' . $place->name . ' atualizado.');
    }

    /** Devolve a quadra ao horário padrão do clube, em todos os dias. */
    public function resetSelfServicePlaceWindows(Place $place)
    {
        LightingSelfServiceWindow::where('place_id', $place->id)->delete();

        return redirect()->to(route('home-assistant.index') . '#self-service')
            ->with('success', $place->name . ' voltou a seguir o horário padrão.');
    }

    /**
     * @return array<int, array{mode?: string, starts_at?: string|null, ends_at?: string|null}>
     */
    private function validateSelfServiceWindows(Request $request, bool $withMode = false): array
    {
        $rules = [
            'windows'             => 'required|array',
            'windows.*.starts_at' => 'nullable|date_format:H:i|required_with:windows.*.ends_at',
            // `after` e não `after_or_equal`: faixa de duração zero não abre
            // nada, e uma que vira o dia não seria honrada pelo comando manual,
            // truncado na meia-noite.
            'windows.*.ends_at'   => 'nullable|date_format:H:i|after:windows.*.starts_at|required_with:windows.*.starts_at',
        ];

        if ($withMode) {
            $rules['windows.*.mode']      = 'required|in:inherit,closed,custom';
            // Sem isto, "faixa própria" com os campos vazios viraria um
            // fechamento silencioso da quadra.
            $rules['windows.*.starts_at'] .= '|required_if:windows.*.mode,custom';
            $rules['windows.*.ends_at']   .= '|required_if:windows.*.mode,custom';
        }

        $data = $request->validate($rules, [
            'windows.*.ends_at.after'           => 'O horário final precisa ser depois do inicial, e no mesmo dia.',
            'windows.*.ends_at.required_with'   => 'Informe os dois horários, ou nenhum.',
            'windows.*.starts_at.required_with' => 'Informe os dois horários, ou nenhum.',
            'windows.*.starts_at.required_if'   => 'Informe o horário da faixa, ou escolha herdar/fechado.',
            'windows.*.ends_at.required_if'     => 'Informe o horário da faixa, ou escolha herdar/fechado.',
        ]);

        $windows = [];

        foreach ($data['windows'] as $weekday => $range) {
            if (in_array((int) $weekday, LightingSelfServiceWindow::WEEKDAYS, true)) {
                $windows[(int) $weekday] = $range;
            }
        }

        return $windows;
    }

    /* ──────────── Autoatendimento: datas especiais (feriados) ──────────── */

    /**
     * Libera ou bloqueia uma data para o acionamento de luz pelo sócio.
     *
     * A data é única na tabela porque duas regras para o mesmo dia não teriam
     * resposta ("libera ou bloqueia?"): regravar a existente é o comportamento
     * que o painel promete ao mostrar uma linha por data.
     */
    public function storeSelfServiceDate(Request $request)
    {
        $data = $this->validateSelfServiceDate($request);

        LightingSelfServiceDate::updateOrCreate(
            ['date' => $data['date']],
            [
                'mode'       => $data['mode'],
                // Bloqueio é sempre o dia inteiro: guardar horário ali só
                // criaria a expectativa de um bloqueio parcial que não existe.
                'starts_at'  => $data['mode'] === LightingSelfServiceDate::MODE_ALLOW ? ($data['starts_at'] ?? null) : null,
                'ends_at'    => $data['mode'] === LightingSelfServiceDate::MODE_ALLOW ? ($data['ends_at'] ?? null) : null,
                'reason'     => $data['reason'] ?? null,
                'created_by' => auth()->id(),
            ]
        );

        return redirect()->to(route('home-assistant.index') . '#self-service')
            ->with('success', 'Data especial salva.');
    }

    public function destroySelfServiceDate(LightingSelfServiceDate $date)
    {
        $date->delete();

        return redirect()->to(route('home-assistant.index') . '#self-service')
            ->with('success', 'Data especial removida.');
    }

    private function validateSelfServiceDate(Request $request): array
    {
        return $request->validate([
            'date'      => 'required|date',
            'mode'      => 'required|in:allow,block',
            'starts_at' => 'nullable|date_format:H:i|required_with:ends_at',
            'ends_at'   => 'nullable|date_format:H:i|required_with:starts_at|after:starts_at',
            'reason'    => 'nullable|string|max:120',
        ], [
            'ends_at.after'          => 'O horário final precisa ser depois do inicial.',
            'starts_at.required_with' => 'Informe os dois horários, ou nenhum.',
            'ends_at.required_with'   => 'Informe os dois horários, ou nenhum.',
        ]);
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
