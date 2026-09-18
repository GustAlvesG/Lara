<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Replay — Câmeras') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Câmeras</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                O <strong>identificador</strong> é o nome do equipamento no sistema de captura — é por ele que a câmera
                busca a configuração e envia os clipes. Pegue esse valor com quem instalou; inventar um aqui deixa a
                câmera gravando sem conseguir enviar.
            </p>
        </div>

        @include('partials.alerts')

        @include('replay.partials.tabs', ['current' => 'cameras'])

        <div class="mb-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nova câmera</h2>

            <form action="{{ route('replay.cameras.store') }}" method="POST"
                  class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                @csrf

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Quadra</label>
                    <select name="place_id" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        @foreach($places as $place)
                            <option value="{{ $place->id }}">
                                {{ $place->group?->name ? $place->group->name . ' — ' : '' }}{{ $place->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Identificador</label>
                    <input type="text" name="external_id" value="{{ old('external_id') }}" required placeholder="cam-quadra1"
                           class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Nome</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="Quadra 1"
                           class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Posição</label>
                    <input type="text" name="position" value="{{ old('position') }}" placeholder="Lado A"
                           class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                </div>

                <div class="md:col-span-5 flex justify-end">
                    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-xl font-bold shadow hover:bg-emerald-700 transition">
                        Cadastrar
                    </button>
                </div>
            </form>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Até {{ $maxPerPlace }} câmeras por quadra — campo de futebol usa uma por metade.
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($cameras->isEmpty())
                <div class="p-12 text-center text-gray-500 dark:text-gray-400">Nenhuma câmera cadastrada.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Identificador</th>
                                <th class="px-6 py-3">Câmera</th>
                                <th class="px-6 py-3">Quadra</th>
                                <th class="px-6 py-3">Gravando como</th>
                                <th class="px-6 py-3">Último contato</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($cameras as $camera)
                                @php($config = $resolved[$camera->id] ?? null)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 font-mono text-xs text-gray-900 dark:text-white">{{ $camera->external_id }}</td>
                                    <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                        {{ $camera->name }}
                                        @if($camera->position)
                                            <span class="ml-1 text-xs text-gray-500 dark:text-gray-400">({{ $camera->position }})</span>
                                        @endif
                                        @unless($camera->active)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">Inativa</span>
                                        @endunless
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                        {{ $camera->place?->group?->name ? $camera->place->group->name . ' — ' : '' }}{{ $camera->place?->name ?? '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                        @if($config)
                                            {{ \App\Support\Replay\Orientation::label($config['orientation']) }} · {{ $config['clip_seconds'] }}s
                                            <div class="text-xs text-gray-400">
                                                {{ $config['layout'] ? 'layout: ' . $config['layout']->name : 'sem logomarca' }}
                                            </div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($camera->last_seen_at)
                                            <span class="{{ $camera->isSilent() ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}">
                                                {{ $camera->last_seen_at->format('d/m/Y H:i') }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">nunca</span>
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
                                            <button type="submit" class="text-xs text-gray-600 dark:text-gray-300 hover:underline font-medium">
                                                {{ $camera->active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>

                                        <form action="{{ route('replay.cameras.destroy', $camera) }}" method="POST" class="inline ml-3"
                                              onsubmit="return confirm('Remover esta câmera? Os vídeos já gravados continuam disponíveis.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium">Excluir</button>
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
