<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Banco de Horas — Importação
            </h2>
        </div>
    </x-slot>

    <x-slot name="slot">
        <div class="max-w-2xl mx-auto py-16 px-4 sm:px-6 lg:px-8"
             x-data="importPoller('{{ $import->uuid }}', '{{ route('comp-time.import-status.api', $import->uuid) }}', '{{ route('comp-time.index') }}')"
             x-init="start()">

            {{-- Carregando --}}
            <div x-show="status === 'pending' || status === 'processing'"
                 class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg px-8 py-12 text-center space-y-6">
                <div class="flex justify-center">
                    <svg class="animate-spin h-12 w-12 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-lg font-semibold text-gray-800 dark:text-gray-200" x-text="message"></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Isso pode levar alguns instantes. Não feche esta aba.
                    </p>
                </div>
            </div>

            {{-- Concluído (sem duplicatas / após confirmar) --}}
            <div x-show="status === 'completed' && (phase === 'importing' || phase === 'confirming')"
                 x-cloak
                 class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg px-8 py-12 text-center space-y-5">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                        <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">Importação concluída!</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Redirecionando...</p>
                </div>
            </div>

            {{-- Falhou --}}
            <div x-show="status === 'failed'"
                 x-cloak
                 class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg px-8 py-12 text-center space-y-5">
                <div class="flex justify-center">
                    <div class="w-16 h-16 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                        <svg class="w-8 h-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <p class="text-lg font-semibold text-gray-800 dark:text-gray-200">Erro na importação</p>
                    <p class="text-sm text-red-600 dark:text-red-400 mt-1 font-mono break-words" x-text="error"></p>
                </div>
                <a href="{{ route('comp-time.index') }}"
                   class="inline-block mt-4 px-5 py-2.5 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow transition">
                    Tentar novamente
                </a>
            </div>

        </div>
    </x-slot>
</x-app-layout>

<script>
function importPoller(uuid, apiUrl, redirectUrl) {
    return {
        status: '{{ $import->status }}',
        phase:  '{{ $import->phase }}',
        error:  null,
        message: 'Analisando arquivo...',

        start() {
            if (this.status === 'completed' || this.status === 'failed') {
                this.handleCompleted({ status: this.status, phase: this.phase });
            } else {
                this.poll();
            }
        },

        async poll() {
            try {
                const res  = await fetch(apiUrl);
                const data = await res.json();

                this.status = data.status;
                this.phase  = data.phase;
                this.error  = data.error ?? null;

                if (data.status === 'processing') {
                    this.message = data.phase === 'confirming'
                        ? 'Processando importação e recalculando saldos...'
                        : 'Analisando arquivo...';
                }

                if (data.status === 'failed' || data.status === 'completed') {
                    this.handleCompleted(data);
                    return;
                }

                setTimeout(() => this.poll(), 2000);
            } catch (_) {
                setTimeout(() => this.poll(), 3000);
            }
        },

        handleCompleted(data) {
            if (data.status === 'failed') return;

            if (data.phase === 'detecting' && data.has_duplicates) {
                window.location.href = data.preview_url;
                return;
            }

            // importing ou confirming: redireciona via rota que seta flash
            setTimeout(() => { window.location.href = data.redirect_url; }, 1200);
        }
    };
}
</script>
