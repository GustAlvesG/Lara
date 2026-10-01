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
     class="mb-6 bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">

    <button type="button" @click="open = !open"
        class="w-full p-6 flex items-center justify-between text-left hover:bg-subtle transition">
        <div class="flex items-center gap-4">
            <div class="bg-grena/10 text-grena-ink p-2 rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 16.5V9m0 0l-3 3m3-3l3 3M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-4.19a1.5 1.5 0 01-1.06-.44l-1.12-1.12a1.5 1.5 0 00-1.06-.44H6.75A2.25 2.25 0 004.5 6.25v11a2.25 2.25 0 002.25 2.25z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-ink">Importar por planilha</h2>
                <p class="text-sm text-ink-2">Cadastre vários de uma vez a partir de um arquivo .xlsx.</p>
            </div>
        </div>
        <svg class="w-5 h-5 text-ink-3 transition-transform" :class="open && 'rotate-180'"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="open" x-cloak class="px-6 pb-6 border-t border-line pt-6">

        @if(session('import_errors'))
            <div class="mb-6 rounded-xl border border-danger/40 bg-danger-soft p-4">
                <p class="font-bold text-danger text-sm mb-2">
                    {{ count(session('import_errors')) }} problema(s) encontrado(s) — nenhum registro foi importado:
                </p>
                <ul class="text-sm text-danger space-y-1 max-h-64 overflow-y-auto list-disc list-inside">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <ol class="text-sm text-ink-2 space-y-2 mb-6 list-decimal list-inside">
            <li>Baixe o arquivo modelo e preencha uma linha por registro (apague a linha de exemplo).</li>
            <li>Não altere nem remova as colunas do cabeçalho. Campos com <span class="font-bold">*</span> são obrigatórios.</li>
            <li>Envie o arquivo. A importação é tudo-ou-nada: havendo qualquer erro, nada é gravado.</li>
        </ol>

        @if($hint)
            <p class="mb-6 text-sm rounded-xl bg-warn-soft border border-warn/40 text-warn p-4">
                {{ $hint }}
            </p>
        @endif

        <div class="mb-6">
            <p class="text-xs font-bold text-ink-2 uppercase tracking-wide mb-2">Colunas esperadas</p>
            <div class="flex flex-wrap gap-2">
                @foreach($columns as $label)
                    <span class="px-3 py-1 rounded-lg text-xs font-semibold bg-subtle text-ink">{{ $label }}</span>
                @endforeach
            </div>
        </div>

        <form action="{{ $action }}" method="POST" enctype="multipart/form-data"
              class="flex flex-col sm:flex-row sm:items-end gap-4">
            @csrf

            <div class="flex-1">
                <label class="block text-sm font-bold text-ink mb-1">Arquivo .xlsx <span class="text-danger">*</span></label>
                <input type="file" name="spreadsheet" accept=".xlsx" required
                    @change="fileName = $event.target.files[0]?.name ?? ''"
                    class="w-full text-sm text-ink-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-subtle file:text-ink hover:file:bg-line cursor-pointer">
                @error('spreadsheet')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="flex gap-3">
                <a href="{{ $templateRoute }}"
                   class="px-5 py-3 rounded-xl font-bold text-sm text-grena-ink border-2 border-grena hover:bg-grena hover:text-white transition whitespace-nowrap">
                    Baixar modelo
                </a>
                <button type="submit" :disabled="!fileName"
                    class="px-5 py-3 bg-grena text-white rounded-xl font-bold text-sm shadow-card hover:bg-grena-hover transition disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                    Importar
                </button>
            </div>
        </form>
    </div>
</div>
