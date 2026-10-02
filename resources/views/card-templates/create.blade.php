<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Novo modelo de carteirinha" :back="route('card-templates.index')">
            Envie as imagens de frente e verso e posicione os campos.
        </x-page-title>

        @include('partials.alerts')

        <div class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @include('card-templates.partials.form')
        </div>
    </x-page>
</x-app-layout>
