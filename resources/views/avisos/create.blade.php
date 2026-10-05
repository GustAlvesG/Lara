<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Novo aviso" :back="route('avisos.index')">
            Escolha quem vê e, se quiser, agende lembretes.
        </x-page-title>

        <form action="{{ route('avisos.store') }}" method="POST" enctype="multipart/form-data" id="aviso-form"
              class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            @include('avisos.partials.form')

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ route('avisos.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button type="submit"><x-icon name="check" /> Publicar aviso</x-primary-button>
            </div>
        </form>
    </x-page>

    @include('avisos.partials.editor-scripts')
</x-app-layout>
