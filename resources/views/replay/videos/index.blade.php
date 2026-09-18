<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Replay — Vídeos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Vídeos</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Todo clipe vive {{ $retentionDays }} dias contados da gravação e é apagado automaticamente — sem exceção.
                Baixe o que for usar em campanha.
            </p>
        </div>

        @include('partials.alerts')

        @include('replay.partials.tabs', ['current' => 'videos'])

        <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Esporte</label>
                <select name="place_group_id"
                        class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="">Todos</option>
                    @foreach($groups as $group)
                        <option value="{{ $group->id }}" @selected(request('place_group_id') == $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Quadra</label>
                <select name="place_id"
                        class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="">Todas</option>
                    @foreach($places as $place)
                        <option value="{{ $place->id }}" @selected(request('place_id') == $place->id)>
                            {{ $place->group?->name ? $place->group->name . ' — ' : '' }}{{ $place->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Data</label>
                <input type="date" name="date" value="{{ request('date') }}"
                       class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Reserva</label>
                <select name="linked"
                        class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    <option value="">Todos</option>
                    <option value="yes" @selected(request('linked') === 'yes')>Com sócio vinculado</option>
                    <option value="no" @selected(request('linked') === 'no')>Sem reserva</option>
                </select>
            </div>

            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg text-sm font-bold hover:bg-gray-900 transition">Filtrar</button>
            <a href="{{ route('replay.videos.index') }}" class="px-4 py-2 text-sm font-bold text-gray-600 dark:text-gray-300 hover:underline">Limpar</a>
        </form>

        @if($videos->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-12 text-center text-gray-500 dark:text-gray-400">
                Nenhum vídeo encontrado com esses filtros.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($videos as $video)
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden flex flex-col">
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
                                    <p class="font-bold text-gray-900 dark:text-white">{{ $video->place?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $video->place?->group?->name ?? '—' }} · {{ $video->camera?->name ?? 'câmera removida' }}
                                    </p>
                                </div>

                                @if($video->daysLeft() <= 1)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 whitespace-nowrap">
                                        {{ $video->daysLeft() === 0 ? 'expira hoje' : '1 dia' }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300 whitespace-nowrap">
                                        {{ $video->daysLeft() }} dias
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $video->recorded_at?->format('d/m/Y H:i:s') }} · {{ $video->duration_seconds }}s
                                · {{ number_format($video->size_bytes / 1048576, 1, ',', '.') }} MB
                            </p>

                            @if($video->member_id)
                                <span class="self-start inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                    Reserva de {{ $video->member?->name ?? 'sócio' }}
                                </span>
                            @endif

                            <div class="mt-auto pt-3 flex items-center justify-between">
                                <a href="{{ $video->url() }}" download
                                   class="text-emerald-600 dark:text-emerald-400 hover:underline font-medium text-xs">Baixar</a>

                                <form action="{{ route('replay.videos.destroy', $video) }}" method="POST"
                                      onsubmit="return confirm('Remover este vídeo definitivamente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Excluir</button>
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
