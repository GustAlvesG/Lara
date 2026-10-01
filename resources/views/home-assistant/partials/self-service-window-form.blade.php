{{--
    Modal de horário de autoatendimento de UMA quadra.

    Parâmetros:
      $place    Place                              a quadra
      $proprio  ?Collection<int, Window>           exceções dela, por dia da semana
      $padrao   Collection<int, Window>            o padrão do clube, por dia da semana

    Cada dia tem três destinos, e o `mode` os separa porque "sem horário" é
    ambíguo: pode ser "segue o clube" ou "esta quadra fica apagada". A tela
    obriga a escolher.
--}}
@php
    $modalId = 'ss-window-' . $place->id;
    $useOld  = old('_modal') === $modalId;
    $dias    = \App\Models\LightingSelfServiceWindow::WEEKDAYS;

    $hora = fn ($valor) => $valor ? substr($valor, 0, 5) : '';

    // Modo atual de cada dia: sem linha própria é herança; linha sem horário é
    // fechamento; linha com horário é faixa própria.
    $modoDe = function (int $dia) use ($proprio) {
        $linha = $proprio?->get($dia);

        if (! $linha) {
            return 'inherit';
        }

        return $linha->isClosed() ? 'closed' : 'custom';
    };

    $rotuloPadrao = function (int $dia) use ($padrao, $hora) {
        $linha = $padrao->get($dia);

        return $linha && ! $linha->isClosed()
            ? $hora($linha->starts_at) . '–' . $hora($linha->ends_at)
            : 'fechado';
    };
@endphp

<x-modal :name="$modalId" :show="$useOld && $errors->any()" maxWidth="2xl" focusable>
    <form method="POST" action="{{ route('home-assistant.self-service.windows.place', $place) }}">
        @csrf
        <input type="hidden" name="_modal" value="{{ $modalId }}">

        <div class="flex items-center justify-between px-6 py-4 border-b border-line">
            <div class="min-w-0">
                <h3 class="text-lg font-bold text-ink truncate">Horário · {{ $place->name }}</h3>
                <p class="text-xs text-ink-2">
                    {{ $place->group?->name ?? 'Sem grupo' }} — o que não for definido aqui segue o horário padrão do clube.
                </p>
            </div>
            <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                class="p-1 rounded-lg text-ink-3 hover:text-ink-2" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-3 max-h-[60vh] overflow-y-auto">
            @if($useOld && $errors->any())
                <div class="p-3 rounded-lg bg-danger-soft text-sm text-danger">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <p class="text-xs text-ink-2">
                Quadra coberta escurece antes: é aqui que ela ganha uma abertura mais cedo sem
                adiantar a luz das outras.
            </p>

            @foreach($dias as $dia)
                @php
                    $linha = $proprio?->get($dia);
                    $modo  = $useOld ? old('windows.' . $dia . '.mode', 'inherit') : $modoDe($dia);
                    $ini   = $useOld ? old('windows.' . $dia . '.starts_at') : $hora($linha?->starts_at);
                    $fim   = $useOld ? old('windows.' . $dia . '.ends_at')   : $hora($linha?->ends_at);
                @endphp
                <div x-data="{ mode: '{{ $modo }}' }"
                    class="p-3 rounded-xl border {{ $dia === 7 ? 'border-grena/40 bg-grena-tint/40' : 'border-line' }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="w-20 shrink-0 text-xs font-bold text-ink-2">
                            {{ \App\Models\LightingSelfServiceWindow::weekdayName($dia) }}
                        </p>

                        <select name="windows[{{ $dia }}][mode]" x-model="mode"
                            class="px-2 py-1.5 text-sm rounded-lg border-line-strong focus:border-grena focus:ring-grena-tint">
                            <option value="inherit">Seguir o padrão ({{ $rotuloPadrao($dia) }})</option>
                            <option value="custom">Horário próprio</option>
                            <option value="closed">Fechada neste dia</option>
                        </select>

                        <div class="flex items-center gap-1.5" x-show="mode === 'custom'" x-cloak>
                            <input type="time" name="windows[{{ $dia }}][starts_at]" value="{{ $ini }}"
                                class="px-2 py-1.5 text-sm rounded-lg border-line-strong focus:border-grena focus:ring-grena-tint">
                            <span class="text-xs text-ink-3">às</span>
                            <input type="time" name="windows[{{ $dia }}][ends_at]" value="{{ $fim }}"
                                class="px-2 py-1.5 text-sm rounded-lg border-line-strong focus:border-grena focus:ring-grena-tint">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center justify-between gap-3 px-6 py-4 bg-subtle border-t border-line">
            @if($proprio && $proprio->isNotEmpty())
                <button type="submit" form="ss-window-reset-{{ $place->id }}"
                    class="text-xs font-semibold text-danger hover:text-grena-ink">
                    Voltar ao padrão em todos os dias
                </button>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('close-modal', '{{ $modalId }}')"
                    class="px-4 py-2 text-sm font-semibold text-ink-2 hover:text-ink">
                    Cancelar
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-grena hover:bg-grena-hover text-white text-sm font-semibold rounded-lg shadow-card transition">
                    Salvar
                </button>
            </div>
        </div>
    </form>

    {{-- Fora do formulário acima: um form dentro de outro não é HTML válido. --}}
    <form id="ss-window-reset-{{ $place->id }}" method="POST"
        action="{{ route('home-assistant.self-service.windows.reset', $place) }}"
        onsubmit="return confirm('Remover o horário próprio de {{ $place->name }}?')">
        @csrf
        @method('DELETE')
    </form>
</x-modal>
