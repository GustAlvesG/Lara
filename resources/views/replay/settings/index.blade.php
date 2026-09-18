<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Replay — Configuração de Vídeo') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Configuração de Vídeo</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Orientação e duração do clipe, por esporte ou por quadra. A quadra sempre vence o esporte;
                sem configuração, vale o padrão do sistema ({{ \App\Support\Replay\Orientation::label(null) }},
                {{ \App\Support\Replay\Orientation::DEFAULT_CLIP_SECONDS }}s).
            </p>
        </div>

        @include('partials.alerts')

        @include('replay.partials.tabs', ['current' => 'settings'])

        @forelse($rows as $row)
            @php($group = $row['group'])
            <div class="mb-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">

                <div class="px-6 py-5 bg-gray-50 dark:bg-gray-900/40 border-b border-gray-100 dark:border-gray-700">
                    <form action="{{ route('replay.settings.store') }}" method="POST"
                          class="flex flex-col lg:flex-row lg:items-end gap-4">
                        @csrf
                        <input type="hidden" name="owner_type" value="group">
                        <input type="hidden" name="owner_id" value="{{ $group->id }}">

                        <div class="flex-1">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $group->name }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $group->places->count() }} quadra(s) — o que estiver aqui vale para todas as que não tiverem configuração própria.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Orientação</label>
                            <select name="orientation"
                                    class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                @foreach($orientations as $value => $label)
                                    <option value="{{ $value }}" @selected(($row['setting']->orientation ?? null) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Duração (s)</label>
                            <input type="number" name="clip_seconds" min="{{ $minSeconds }}" max="{{ $maxSeconds }}"
                                   value="{{ $row['setting']->clip_seconds ?? \App\Support\Replay\Orientation::DEFAULT_CLIP_SECONDS }}"
                                   class="w-28 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <button type="submit"
                                class="px-6 py-2 bg-emerald-600 text-white rounded-xl font-bold shadow hover:bg-emerald-700 transition">
                            Salvar esporte
                        </button>
                    </form>
                </div>

                @if($row['places']->isEmpty())
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">Nenhuma quadra neste esporte.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th class="px-6 py-3">Quadra</th>
                                    <th class="px-6 py-3">Câmeras</th>
                                    <th class="px-6 py-3">Configuração efetiva</th>
                                    <th class="px-6 py-3">Configuração própria da quadra</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($row['places'] as $item)
                                    @php($place = $item['place'])
                                    @php($effective = $item['effective'])
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                        <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                                            {{ $place->name }}
                                        </td>

                                        <td class="px-6 py-4">
                                            @forelse($item['cameras'] as $camera)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                                    {{ $camera->active ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' }}">
                                                    {{ $camera->name }}
                                                </span>
                                            @empty
                                                <span class="text-xs text-gray-400">sem câmera</span>
                                            @endforelse
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="text-gray-800 dark:text-gray-200 font-medium">
                                                {{ \App\Support\Replay\Orientation::label($effective['orientation']) }} · {{ $effective['clip_seconds'] }}s
                                            </span>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                                @if($effective['source'] === 'place') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                                @elseif($effective['source'] === 'group') bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                                                @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400 @endif">
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
                                                            class="px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                                        @foreach($orientations as $value => $label)
                                                            <option value="{{ $value }}"
                                                                @selected(($item['own']->orientation ?? $effective['orientation']) === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>

                                                    <input type="number" name="clip_seconds" min="{{ $minSeconds }}" max="{{ $maxSeconds }}"
                                                           value="{{ $item['own']->clip_seconds ?? $effective['clip_seconds'] }}"
                                                           class="w-20 px-3 py-1.5 text-xs border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white">

                                                    <button type="submit"
                                                            class="px-3 py-1.5 bg-gray-800 text-white rounded-lg text-xs font-bold hover:bg-gray-900 transition">
                                                        Salvar
                                                    </button>
                                                </form>

                                                @if($item['own'])
                                                    <form action="{{ route('replay.settings.destroy', $item['own']) }}" method="POST"
                                                          onsubmit="return confirm('Remover a configuração própria? A quadra volta a herdar do esporte.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline font-medium">
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
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-12 text-center text-gray-500 dark:text-gray-400">
                Nenhum esporte cadastrado.
            </div>
        @endforelse
    </div>
</div>
</x-app-layout>
