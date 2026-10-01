<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title title="Editar placa" :back="route('parking-authorizations.index')">
            <x-plate :plate="$authorization->plate" size="sm" />
        </x-page-title>

        <form method="POST" action="{{ route('parking-authorizations.update', $authorization) }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            @method('PUT')

            @include('parking.authorizations.partials.form', ['item' => $authorization])

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ route('parking-authorizations.index') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> Atualizar</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
