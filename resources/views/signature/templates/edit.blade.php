<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Revisar modelo" :back="route('signature-templates.show', $template)">
            {{ $template->name }}
        </x-page-title>

        @include('partials.alerts')

        <div class="mb-6 rounded-2xl border border-warn/40 bg-warn-soft p-5">
            <p class="text-sm text-warn font-semibold mb-1">
                Salvar cria a versão {{ $template->version + 1 }} — não altera a versão {{ $template->version }}.
            </p>
            <p class="text-xs text-warn leading-relaxed">
                Os documentos já emitidos continuam apontando para a versão em que foram gerados, e seguem
                imprimindo o texto que as pessoas leram e assinaram. Novos documentos passam a usar a versão nova.
            </p>
        </div>

        <form action="{{ route('signature-templates.update', $template) }}" method="POST">
            @csrf
            @method('PUT')
            @include('signature.templates.partials.form', ['template' => $template])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('signature-templates.show', $template) }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">
                    Salvar como versão {{ $template->version + 1 }}
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
