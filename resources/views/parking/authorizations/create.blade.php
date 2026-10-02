<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Nova placa autorizada" :back="route('parking-authorizations.index')">
            A placa passa a abrir a cancela até a data de validade.
        </x-page-title>

        <form method="POST" action="{{ route('parking-authorizations.store') }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf

            @include('parking.authorizations.partials.form')

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ route('parking-authorizations.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> Cadastrar</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
