<x-app-layout :bootstrap-grid="false">

<div>
    <div class="mx-auto w-full max-w-[1200px] px-4 pb-14 pt-6 sm:px-6 sm:pt-8 lg:px-8">

        <div class="mb-8">
            <h2 class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink sm:text-[26px]">Configuração de Vídeo</h2>
            <p class="mt-1.5 max-w-3xl text-[15px] leading-relaxed text-ink-2">
                Orientação e duração do clipe, por esporte ou por quadra. A quadra sempre vence o esporte;
                sem configuração, vale o padrão do sistema ({{ \App\Support\Replay\Orientation::label(null) }},
                {{ \App\Support\Replay\Orientation::DEFAULT_CLIP_SECONDS }}s).
            </p>
        </div>

        @include('partials.alerts')

        {{-- Faixa do módulo (identidade do RePET, 11/11/2026). --}}
        @php
            $heroStats = [['label' => 'Esportes', 'value' => count($rows)]];
            foreach ([['replay.cameras.index', 'Câmeras', null], ['replay.videos.index', 'Vídeos', null], ['replay.broadcasts.index', 'Transmissões', \App\Authorization\Permissions::REPLAY_TRANSMISSAO]] as [$rota, $rotulo, $perm]) {
                if (! $perm || auth()->user()?->can($perm)) {
                    $heroStats[] = ['label' => $rotulo, 'value' => null, 'href' => route($rota)];
                }
            }
        @endphp
        <x-mod-hero variant="rec" eyebrow="RePET" title="O lance gravado, pronto para baixar" :stats="$heroStats" label="Resumo do RePET" class="mb-8">
            Formato do clipe, logomarca, câmeras e vídeos: as etapas do mesmo trabalho, nesta ordem.
        </x-mod-hero>


        @forelse($rows as $row)
            @php($group = $row['group'])
            <div class="mb-8 bg-surface rounded-card shadow-card overflow-hidden">

                <div class="px-6 py-5 bg-subtle border-b border-line">
                    <form action="{{ route('replay.settings.store') }}" method="POST"
                          class="flex flex-col lg:flex-row lg:items-end gap-4">
                        @csrf
                        <input type="hidden" name="owner_type" value="group">
                        <input type="hidden" name="owner_id" value="{{ $group->id }}">

                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-ink">{{ $group->name }}</h2>
                            <p class="text-xs text-ink-2">
                                {{ $group->places->count() }} quadra(s) — o que estiver aqui vale para todas as que não tiverem configuração própria.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Orientação</label>
                            <select name="orientation"
                                    class="px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                                @foreach($orientations as $value => $label)
                                    <option value="{{ $value }}" @selected(($row['setting']->orientation ?? null) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Duração (s)</label>
                            <input type="number" name="clip_seconds" min="{{ $minSeconds }}" max="{{ $maxSeconds }}"
                                   value="{{ $row['setting']->clip_seconds ?? \App\Support\Replay\Orientation::DEFAULT_CLIP_SECONDS }}"
                                   class="w-28 px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        </div>

                        <button type="submit"
                                class="px-6 py-2 bg-grena text-white rounded-xl font-bold shadow hover:bg-grena-hover transition">
                            Salvar esporte
                        </button>
                    </form>
                </div>

                @if($row['places']->isEmpty())
                    <div class="p-8 text-center text-ink-2">Nenhuma quadra neste esporte.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                                <tr>
                                    <th class="px-6 py-3">Quadra</th>
                                    <th class="px-6 py-3">Câmeras</th>
                                    <th class="px-6 py-3">Configuração efetiva</th>
                                    <th class="px-6 py-3">Configuração própria da quadra</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach($row['places'] as $item)
                                    @php($place = $item['place'])
                                    @php($effective = $item['effective'])
                                    <tr class="hover:bg-subtle transition">
                                        <td class="px-6 py-4 font-semibold text-ink whitespace-nowrap">
                                            {{ $place->name }}
                                        </td>

                                        <td class="px-6 py-4">
                                            @forelse($item['cameras'] as $camera)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $camera->active ? 'bg-grena-tint text-grena-ink ' : 'bg-subtle text-ink-2 ' }}">
                                                    {{ $camera->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-ink-3">sem câmera</span>
                                            @endforelse
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-ink font-medium">
                                                {{ \App\Support\Replay\Orientation::label($effective['orientation']) }} · {{ $effective['clip_seconds'] }}s
                                            </span>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold @if($effective['source'] === 'place') bg-ok-soft text-ok @elseif($effective['source'] === 'group') bg-warn-soft text-warn @else bg-subtle text-ink-2 @endif">
                                                @if($effective['source'] === 'place') própria
                                                @elseif($effective['source'] === 'group') herdado do esporte
                                                @else padrão do sistema @endif
                                            </span>
                                        </td>

                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <form action="{{ route('replay.settings.store') }}" method="POST" class="flex items-center gap-2">
                                                    @csrf
                                                    <input type="hidden" name="owner_type" value="place">
                                                    <input type="hidden" name="owner_id" value="{{ $place->id }}">

                                                    <select name="orientation"
                                                            class="px-3 py-1.5 text-xs border border-line rounded-lg bg-surface text-ink">
                                                        @foreach($orientations as $value => $label)
                                                            <option value="{{ $value }}"
                                                                @selected(($item['own']->orientation ?? $effective['orientation']) === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>

                                                    <input type="number" name="clip_seconds" min="{{ $minSeconds }}" max="{{ $maxSeconds }}"
                                                           value="{{ $item['own']->clip_seconds ?? $effective['clip_seconds'] }}"
                                                           class="w-20 px-3 py-1.5 text-xs border border-line rounded-lg bg-surface text-ink">

                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-grena text-white rounded-lg text-xs font-bold hover:bg-grena-hover transition">
                                                        Salvar
                                                    </button>
                                                </form>

                                                @if($item['own'])
                                                    <form action="{{ route('replay.settings.destroy', $item['own']) }}" method="POST"
                                                          onsubmit="return confirm('Remover a configuração própria? A quadra volta a herdar do esporte.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs text-danger hover:underline font-medium">
                                                            Voltar a herdar
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-surface rounded-2xl shadow-card p-12 text-center text-ink-2">
                Nenhum esporte cadastrado.
            </div>
        @endforelse
    </div>
</div>
</x-app-layout>
