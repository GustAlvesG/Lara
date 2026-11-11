<x-app-layout :bootstrap-grid="false">

<div>
    <div class="mx-auto w-full max-w-[1200px] px-4 pb-14 pt-6 sm:px-6 sm:pt-8 lg:px-8">

        <div class="mb-8">
            <h2 class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink sm:text-[26px]">Câmeras</h2>
            <p class="mt-1.5 max-w-3xl text-[15px] leading-relaxed text-ink-2">
                O <strong>identificador</strong> é o nome do equipamento no sistema de captura — é por ele que a câmera
                busca a configuração e envia os clipes. Pegue esse valor com quem instalou; inventar um aqui deixa a
                câmera gravando sem conseguir enviar.
            </p>
        </div>

        @include('partials.alerts')


        <div class="mb-8 bg-surface rounded-card shadow-card p-6">
            <h2 class="text-lg font-bold text-ink mb-4">Nova câmera</h2>

            <form action="{{ route('replay.cameras.store') }}" method="POST"
                  class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                @csrf

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Quadra</label>
                    <select name="place_id" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @foreach($places as $place)
                            <option value="{{ $place->id }}">
                                {{ $place->group?->name ? $place->group->name . ' — ' : '' }}{{ $place->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Identificador</label>
                    <input type="text" name="external_id" value="{{ old('external_id') }}" required placeholder="cam-quadra1"
                           class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Nome</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Quadra 1"
                           class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                </div>

                <div>
                    <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Posição</label>
                    <input type="text" name="position" value="{{ old('position') }}" placeholder="Lado A"
                           class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                </div>

                <div class="md:col-span-5 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-grena text-white rounded-xl font-bold shadow hover:bg-grena-hover transition">
                        Cadastrar
                    </button>
                </div>
            </form>

            <p class="mt-3 text-xs text-ink-2">
                Até {{ $maxPerPlace }} câmeras por quadra — campo de futebol usa uma por metade.
            </p>
        </div>

        <div class="bg-surface rounded-card shadow-card overflow-hidden">
            @if($cameras->isEmpty())
                <div class="p-12 text-center text-ink-2">Nenhuma câmera cadastrada.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-6 py-3">Identificador</th>
                                <th class="px-6 py-3">Câmera</th>
                                <th class="px-6 py-3">Quadra</th>
                                <th class="px-6 py-3">Gravando como</th>
                                <th class="px-6 py-3">Último contato</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($cameras as $camera)
                                @php($config = $resolved[$camera->id] ?? null)
                                <tr class="hover:bg-subtle transition">
                                    <td class="px-6 py-4 font-mono text-xs text-ink">{{ $camera->external_id }}</td>
                                    <td class="px-6 py-4 font-semibold text-ink">
                                        {{ $camera->name }}
                                        @if($camera->position)
                                            <span class="ml-1 text-xs text-ink-2">({{ $camera->position }})</span>
                                        @endif
                                        @unless($camera->active)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-subtle text-ink-2">Inativa</span>
                                        @endunless
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">
                                        {{ $camera->place?->group?->name ? $camera->place->group->name . ' — ' : '' }}{{ $camera->place?->name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">
                                        @if($config)
                                            {{ \App\Support\Replay\Orientation::label($config['orientation']) }} · {{ $config['clip_seconds'] }}s
                                            <div class="text-xs text-ink-3">
                                                {{ $config['layout'] ? 'layout: ' . $config['layout']->name : 'sem logomarca' }}
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($camera->last_seen_at)
                                            <span class="{{ $camera->isSilent() ? 'text-danger font-semibold' : 'text-ink-2 ' }}">
                                                {{ $camera->last_seen_at->format('d/m/Y H:i') }}
                                            </span>
                                        @else
                                            <span class="text-xs text-ink-3">nunca</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <form action="{{ route('replay.cameras.update', $camera) }}" method="POST"
                                              class="inline-flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="place_id" value="{{ $camera->place_id }}">
                                            <input type="hidden" name="external_id" value="{{ $camera->external_id }}">
                                            <input type="hidden" name="name" value="{{ $camera->name }}">
                                            <input type="hidden" name="position" value="{{ $camera->position }}">
                                            <input type="hidden" name="active" value="{{ $camera->active ? 0 : 1 }}">
                                            <button type="submit" class="text-xs text-ink-2 hover:underline font-medium">
                                                {{ $camera->active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>

                                        <form action="{{ route('replay.cameras.destroy', $camera) }}" method="POST" class="inline ml-3"
                                              onsubmit="return confirm('Remover esta câmera? Os vídeos já gravados continuam disponíveis.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-danger hover:underline font-medium">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
