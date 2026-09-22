<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Editar Rascunho') }} — {{ $document->title }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <div class="mb-6 flex items-center justify-between">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Modelo</p>
                <p class="font-bold text-gray-900 dark:text-white">
                    {{ $template->name }} <span class="text-gray-400">v{{ $document->template_version }}</span>
                </p>
            </div>
            <a href="{{ route('signature-documents.show', $document) }}" class="text-xs text-gray-500 hover:underline">Voltar ao documento</a>
        </div>

        <form action="{{ route('signature-documents.update', $document) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="signature_template_id" value="{{ $document->signature_template_id }}">

            @include('signature.documents.partials.form', ['document' => $document, 'template' => $template])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('signature-documents.show', $document) }}" class="px-6 py-3 rounded-xl font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">Salvar rascunho</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
