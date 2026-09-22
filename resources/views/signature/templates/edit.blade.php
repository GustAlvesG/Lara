<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Revisar Modelo') }} — {{ $template->name }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-900/20 p-5">
            <p class="text-sm text-amber-900 dark:text-amber-200 font-semibold mb-1">
                Salvar cria a versão {{ $template->version + 1 }} — não altera a versão {{ $template->version }}.
            </p>
            <p class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
                Os documentos já emitidos continuam apontando para a versão em que foram gerados, e seguem
                imprimindo o texto que as pessoas leram e assinaram. Novos documentos passam a usar a versão nova.
            </p>
        </div>

        <form action="{{ route('signature-templates.update', $template) }}" method="POST">
            @csrf
            @method('PUT')
            @include('signature.templates.partials.form', ['template' => $template])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('signature-templates.show', $template) }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">
                    Salvar como versão {{ $template->version + 1 }}
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
