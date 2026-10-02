<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Editar rascunho" :back="route('signature-documents.show', $document)">
            {{ $document->title }}
        </x-page-title>

        @include('partials.alerts')

        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-xs text-ink-2">Modelo</p>
                <p class="font-bold text-ink">
                    {{ $template->name }} <span class="text-ink-3">v{{ $document->template_version }}</span>
                </p>
            </div>
            <a href="{{ route('signature-documents.show', $document) }}" class="text-xs text-ink-2 hover:underline">Voltar ao documento</a>
        </div>

        {{-- Rascunho já preenchido: os passos ficam livres e dá para gravar de qualquer um. --}}
        <form action="{{ route('signature-documents.update', $document) }}" method="POST" data-steps-free>
            @csrf
            @method('PUT')
            <input type="hidden" name="signature_template_id" value="{{ $document->signature_template_id }}">

            @include('signature.documents.partials.form', ['document' => $document, 'template' => $template])

            <div class="mt-6 flex justify-end gap-3" data-step-actions>
                <a href="{{ route('signature-documents.show', $document) }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">Salvar rascunho</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
