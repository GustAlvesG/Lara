<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $template->name }} <span class="text-gray-400">— versão {{ $template->version }}</span>
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

        @include('partials.alerts')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('signature-templates.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">&larr; Modelos</a>

            <div class="flex gap-3">
                @if($template->active)
                    <a href="{{ route('signature-documents.create', ['template' => $template->id]) }}"
                       class="px-5 py-2.5 rounded-xl font-bold text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Emitir documento
                    </a>
                @endif
                <a href="{{ route('signature-templates.edit', $template) }}"
                   class="px-5 py-2.5 bg-[#A00001] text-white rounded-xl font-bold text-sm shadow-lg hover:bg-[#800000] transition">
                    Revisar
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Texto</h3>

                {{-- Prévia do corpo saneado. Sai sem escapar porque é HTML da allow-list, saneado na gravação. --}}
                <div class="prose prose-sm max-w-none dark:prose-invert text-gray-800 dark:text-gray-200 leading-relaxed">
                    {!! $template->body_html !!}
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Regras</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Conferência de identidade</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">
                                {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$template->identity_check] ?? $template->identity_check }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Foto do signatário</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">
                                {{ $template->requires_photo ? 'Exigida' : 'Não capturada' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Prazo de guarda</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">
                                {{ $template->retention_months ? $template->retention_months . ' meses' : 'Padrão (' . config('signature.retention_months') . ' meses)' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 dark:text-gray-400">Situação</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ $template->active ? 'Ativo' : 'Inativo' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
                    <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">Variáveis</h3>
                    @if(empty($template->declaredVariables()))
                        <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma — o texto é fixo.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach($template->declaredVariables() as $variavel)
                                <li class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-xs text-gray-600 dark:text-gray-300">[[{{ $variavel['key'] }}]]</span>
                                    <span class="text-right text-gray-800 dark:text-gray-200">
                                        {{ $variavel['label'] }}
                                        @if($variavel['required'])
                                            <span class="block text-[10px] font-bold text-[#A00001]">obrigatória</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Histórico de versões</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Cada versão continua existindo porque é para ela que apontam os documentos emitidos sob o texto dela.
                </p>
            </div>
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                    <tr>
                        <th class="px-6 py-3">Versão</th>
                        <th class="px-6 py-3">Criada em</th>
                        <th class="px-6 py-3">Documentos emitidos</th>
                        <th class="px-6 py-3">Situação</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($versoes as $versao)
                        <tr class="{{ $versao->id === $template->id ? 'bg-gray-50 dark:bg-gray-900/40' : '' }}">
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">v{{ $versao->version }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $versao->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $usos[$versao->id] ?? 0 }}</td>
                            <td class="px-6 py-4 text-gray-700 dark:text-gray-300">{{ $versao->active ? 'Ativa' : 'Substituída' }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($versao->id !== $template->id)
                                    <a href="{{ route('signature-templates.show', $versao) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline text-xs font-medium">Ver texto</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-app-layout>
