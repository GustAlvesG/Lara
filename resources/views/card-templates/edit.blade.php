<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Editar: ' . $template->name" :back="route('card-templates.index')">
            Troque as imagens ou reposicione os campos. A emissão passa a usar o modelo salvo.
        </x-page-title>

        @include('partials.alerts')

        <div class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @include('card-templates.partials.form')
        </div>
    </x-page>
</x-app-layout>
