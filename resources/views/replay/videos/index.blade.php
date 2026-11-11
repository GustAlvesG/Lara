<x-app-layout :bootstrap-grid="false">

<div>
    <div class="mx-auto w-full max-w-[1200px] px-4 pb-14 pt-6 sm:px-6 sm:pt-8 lg:px-8">

        <div class="mb-8">
            <h2 class="font-display text-[22px] font-semibold leading-tight tracking-tight text-ink sm:text-[26px]">Vídeos</h2>
            <p class="mt-1.5 max-w-3xl text-[15px] leading-relaxed text-ink-2">
                Todo clipe vive {{ $retentionDays }} dias contados da gravação e é apagado automaticamente — sem exceção.
                Baixe o que for usar em campanha.
            </p>
        </div>

        @include('partials.alerts')


        <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Esporte</label>
                <select name="place_group_id"
                        class="px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    <option value="">Todos</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected(request('place_group_id') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Quadra</label>
                <select name="place_id"
                        class="px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    <option value="">Todas</option>
                    @foreach($places as $place)
                        <option value="{{ $place->id }}" @selected(request('place_id') == $place->id)>
                            {{ $place->group?->name ? $place->group->name . ' — ' : '' }}{{ $place->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Data</label>
                <input type="date" name="date" value="{{ request('date') }}"
                       class="px-4 py-2 border border-line rounded-lg bg-surface text-ink">
            </div>

            <div>
                <label class="block text-xs font-bold text-ink-2 uppercase tracking-wider mb-1">Reserva</label>
                <select name="linked"
                        class="px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    <option value="">Todos</option>
                    <option value="yes" @selected(request('linked') === 'yes')>Com sócio vinculado</option>
                    <option value="no" @selected(request('linked') === 'no')>Sem reserva</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-grena text-white rounded-lg text-sm font-bold hover:bg-grena-hover transition">Filtrar</button>
            <a href="{{ route('replay.videos.index') }}" class="px-4 py-2 text-sm font-bold text-ink-2 hover:underline">Limpar</a>
        </form>

        @if($videos->isEmpty())
            <div class="bg-surface rounded-2xl shadow-card p-12 text-center text-ink-2">
                Nenhum vídeo encontrado com esses filtros.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($videos as $video)
                    <div class="bg-surface rounded-card shadow-card overflow-hidden flex flex-col">
                        {{--
                            preload="none": a galeria mostra 24 vídeos por
                            página, e deixar o navegador buscar todos faria a
                            tela puxar centenas de MB à toa.
                        --}}
                        <video src="{{ $video->url() }}" controls preload="none"
                               class="w-full bg-black {{ $video->orientation === 'vertical' ? 'aspect-[9/16]' : 'aspect-video' }}"></video>

                        <div class="p-4 flex-1 flex flex-col gap-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-bold text-ink">{{ $video->place?->name ?? '—' }}</p>
                                    <p class="text-xs text-ink-2">
                                        {{ $video->place?->group?->name ?? '—' }} · {{ $video->camera?->name ?? 'câmera removida' }}
                                    </p>
                                </div>

                                @if($video->daysLeft() <= 1)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-danger-soft text-danger whitespace-nowrap">
                                        {{ $video->daysLeft() === 0 ? 'expira hoje' : '1 dia' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-subtle text-ink-2 whitespace-nowrap">
                                        {{ $video->daysLeft() }} dias
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-ink-2">
                                {{ $video->recorded_at?->format('d/m/Y H:i:s') }} · {{ $video->duration_seconds }}s
                                · {{ number_format($video->size_bytes / 1048576, 1, ',', '.') }} MB
                            </p>

                            @if($video->member_id)
                                <span class="self-start inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-ok-soft text-ok">
                                    Reserva de {{ $video->member?->name ?? 'sócio' }}
                                </span>
                            @endif

                            <div class="mt-auto pt-3 flex items-center justify-between">
                                <a href="{{ $video->url() }}" download
                                   class="text-ok hover:underline font-medium text-xs">Baixar</a>

                                <form action="{{ route('replay.videos.destroy', $video) }}" method="POST"
                                      onsubmit="return confirm('Remover este vídeo definitivamente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-danger hover:underline font-medium text-xs">Excluir</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $videos->links() }}
            </div>
        @endif
    </div>
</div>
</x-app-layout>
