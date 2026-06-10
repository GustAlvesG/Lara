<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center gap-3">
                <a href="{{ route('avisos.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $aviso->title }}
                </h2>
            </div>
            @if(auth()->user()->can('manage avisos') || auth()->user()->hasRole('admin'))
                <a href="{{ route('avisos.edit', $aviso) }}"
                   class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Editar
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">

                @if($aviso->image)
                    <img src="{{ asset('images/avisos/' . $aviso->image) }}"
                         alt="{{ $aviso->title }}"
                         class="w-full max-h-64 object-cover">
                @endif

                <div class="p-6 space-y-4">

                    {{-- Badges de status --}}
                    @php
                        $privacyConfig = [
                            'pessoa'  => ['label' => '🔒 Pessoal', 'class' => 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300'],
                            'setor'   => ['label' => '👥 Setor',   'class' => 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300'],
                            'publico' => ['label' => '🌐 Público', 'class' => 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300'],
                        ];
                        $privacyInfo = $privacyConfig[$aviso->privacy] ?? $privacyConfig['setor'];
                    @endphp
                    <div class="flex flex-wrap gap-2">
                        <span class="px-2 py-1 text-xs rounded-full {{ $privacyInfo['class'] }}">
                            {{ $privacyInfo['label'] }}
                        </span>

                        @if($aviso->isExpired())
                            <span class="px-2 py-1 text-xs rounded-full bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                Expirado em {{ $aviso->expires_at->format('d/m/Y') }}
                            </span>
                        @elseif($aviso->expires_at)
                            <span class="px-2 py-1 text-xs rounded-full {{ $aviso->expiresSoon() ? 'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300' : 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' }}">
                                Expira em {{ $aviso->expires_at->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>

                    {{-- Conteúdo --}}
                    @if($aviso->content)
                        <div class="prose prose-sm dark:prose-invert max-w-none text-gray-700 dark:text-gray-300 leading-relaxed aviso-content">
                            {!! $aviso->content !!}
                        </div>
                    @endif

                    {{-- Lembretes --}}
                    @if($aviso->lembretes->isNotEmpty())
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-700">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wide">Lembretes</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($aviso->lembretes as $lembrete)
                                    <span class="flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-full
                                        {{ $lembrete->sent
                                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                                            : 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300' }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                        {{ $lembrete->remind_at->format('d/m/Y H:i') }}
                                        @if($lembrete->sent)
                                            <span class="opacity-60">(enviado)</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Rodapé --}}
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 text-sm text-gray-400 dark:text-gray-500">
                        Publicado por
                        <span class="font-medium text-gray-600 dark:text-gray-400">{{ $aviso->creator->name ?? '—' }}</span>
                        em {{ $aviso->created_at->format('d/m/Y \à\s H:i') }}
                    </div>
                </div>
            </div>

            {{-- Histórico de acessos (somente managers/admins) --}}
            @if($canManage && $viewHistory->isNotEmpty())
                <div class="mt-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden"
                     x-data="{ open: false }">
                    <button type="button" @click="open = !open"
                            class="w-full flex justify-between items-center px-6 py-4 text-sm font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Histórico de acessos
                            <span class="px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-xs">
                                {{ $viewHistory->count() }} {{ $viewHistory->count() === 1 ? 'pessoa' : 'pessoas' }}
                            </span>
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-collapse class="border-t border-gray-100 dark:border-gray-700">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    <th class="px-6 py-2 text-left font-medium">Usuário</th>
                                    <th class="px-6 py-2 text-left font-medium">Último acesso</th>
                                    <th class="px-6 py-2 text-right font-medium">Acessos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($viewHistory as $entry)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-6 py-3 text-gray-800 dark:text-gray-200 font-medium">
                                            {{ $entry['user']->name ?? '—' }}
                                        </td>
                                        <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                            {{ $entry['last_view']->format('d/m/Y \à\s H:i') }}
                                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">
                                                ({{ $entry['last_view']->diffForHumans() }})
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-right">
                                            <span class="px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300 text-xs font-medium">
                                                {{ $entry['count'] }}x
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <x-slot name="css">
        <style>
            .aviso-content b, .aviso-content strong { font-weight: 700; }
            .aviso-content i, .aviso-content em { font-style: italic; }
            .aviso-content u { text-decoration: underline; }
        </style>
    </x-slot>
</x-app-layout>
