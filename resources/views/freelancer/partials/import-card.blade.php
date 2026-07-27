@php
    /**
     * Bloco de importação em massa por planilha.
     *
     * @var string $action        rota que recebe o arquivo
     * @var string $templateRoute rota do arquivo modelo
     * @var array  $columns       campo => rótulo, apenas para exibir o formato esperado
     * @var string $hint          observação específica do módulo
     */
    $hint = $hint ?? null;
@endphp

<div x-data="{ open: {{ session('import_errors') ? 'true' : 'false' }}, fileName: '' }"
     class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">

    <button type="button" @click="open = !open"
        class="w-full p-6 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
        <div class="flex items-center gap-4">
            <div class="bg-[#A00001]/10 text-[#A00001] p-2 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 16.5V9m0 0l-3 3m3-3l3 3M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-4.19a1.5 1.5 0 01-1.06-.44l-1.12-1.12a1.5 1.5 0 00-1.06-.44H6.75A2.25 2.25 0 004.5 6.25v11a2.25 2.25 0 002.25 2.25z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Importar por planilha</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Cadastre vários de uma vez a partir de um arquivo .xlsx.</p>
            </div>
        </div>
        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open && 'rotate-180'"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="open" x-cloak class="px-6 pb-6 border-t border-gray-50 dark:border-gray-700 pt-6">

        @if(session('import_errors'))
            <div class="mb-6 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
                <p class="font-bold text-red-700 dark:text-red-300 text-sm mb-2">
                    {{ count(session('import_errors')) }} problema(s) encontrado(s) — nenhum registro foi importado:
                </p>
                <ul class="text-sm text-red-700 dark:text-red-300 space-y-1 max-h-64 overflow-y-auto list-disc list-inside">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <ol class="text-sm text-gray-600 dark:text-gray-300 space-y-2 mb-6 list-decimal list-inside">
            <li>Baixe o arquivo modelo e preencha uma linha por registro (apague a linha de exemplo).</li>
            <li>Não altere nem remova as colunas do cabeçalho. Campos com <span class="font-bold">*</span> são obrigatórios.</li>
            <li>Envie o arquivo. A importação é tudo-ou-nada: havendo qualquer erro, nada é gravado.</li>
        </ol>

        @if($hint)
            <p class="mb-6 text-sm rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200 p-4">
                {{ $hint }}
            </p>
        @endif

        <div class="mb-6">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Colunas esperadas</p>
            <div class="flex flex-wrap gap-2">
                @foreach($columns as $label)
                    <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">{{ $label }}</span>
                @endforeach
            </div>
        </div>

        <form action="{{ $action }}" method="POST" enctype="multipart/form-data"
              class="flex flex-col sm:flex-row sm:items-end gap-4">
            @csrf

            <div class="flex-1">
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Arquivo .xlsx <span class="text-red-500">*</span></label>
                <input type="file" name="spreadsheet" accept=".xlsx" required
                    @change="fileName = $event.target.files[0]?.name ?? ''"
                    class="w-full text-sm text-gray-600 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-gray-100 dark:file:bg-gray-700 file:text-gray-700 dark:file:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-600 cursor-pointer">
                @error('spreadsheet')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3">
                <a href="{{ $templateRoute }}"
                   class="px-5 py-3 rounded-xl font-bold text-sm text-[#A00001] border-2 border-[#A00001] hover:bg-[#A00001] hover:text-white transition whitespace-nowrap">
                    Baixar modelo
                </a>
                <button type="submit" :disabled="!fileName"
                    class="px-5 py-3 bg-[#A00001] text-white rounded-xl font-bold text-sm shadow-lg hover:bg-[#800000] transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                    Importar
                </button>
            </div>
        </form>
    </div>
</div>
