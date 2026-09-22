<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Documentos para Assinatura') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Documentos</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Termos, fichas e contratos assinados no tablet do balcão.
                </p>
            </div>

            @can('create', \App\Models\SignatureDocument::class)
                <a href="{{ route('signature-documents.create') }}" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Novo Documento
                </a>
            @endcan
        </div>

        @include('partials.alerts')

        <form method="GET" class="mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[220px]">
                <label for="q" class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Buscar</label>
                <input type="text" name="q" id="q" value="{{ $busca }}"
                       placeholder="Título, nome do signatário, CPF ou código de validação"
                       class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
            </div>
            <div>
                <label for="status" class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">Situação</label>
                <select name="status" id="status"
                        class="rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-[#A00001] focus:ring-[#A00001]">
                    <option value="">Todas</option>
                    @foreach(\App\Models\SignatureDocument::STATUS_LABELS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl font-bold text-sm bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                Filtrar
            </button>
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($documents->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum documento encontrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Documento</th>
                                <th class="px-6 py-3">Signatários</th>
                                <th class="px-6 py-3">Situação</th>
                                <th class="px-6 py-3">Criado em</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($documents as $document)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900 dark:text-white">{{ $document->title }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $document->template?->name }} v{{ $document->template_version }}
                                            @if($document->validation_code)
                                                · <span class="font-mono">{{ $document->validation_code }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300 text-xs">
                                        @foreach($document->signers as $signer)
                                            <div>{{ $signer->name }} <span class="text-gray-400">— {{ $signer->statusLabel() }}</span></div>
                                        @endforeach
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 rounded-full text-xs font-bold
                                            @class([
                                                'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => $document->status === \App\Models\SignatureDocument::STATUS_DRAFT,
                                                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' => $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE,
                                                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' => in_array($document->status, [\App\Models\SignatureDocument::STATUS_SIGNED, \App\Models\SignatureDocument::STATUS_FINALIZED], true),
                                                'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' => in_array($document->status, [\App\Models\SignatureDocument::STATUS_REFUSED, \App\Models\SignatureDocument::STATUS_CANCELED, \App\Models\SignatureDocument::STATUS_EXPIRED], true),
                                            ])">
                                            {{ $document->statusLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 dark:text-gray-300 text-xs">
                                        {{ $document->created_at?->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap">
                                        <a href="{{ route('signature-documents.show', $document) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Abrir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                    {{ $documents->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
