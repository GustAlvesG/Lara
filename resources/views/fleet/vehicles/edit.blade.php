<x-app-layout :bootstrap-grid="false">
    <x-page narrow>
        <x-page-title :title="'Editar ' . $vehicle->name" :back="route('fleet.vehicles')" />

        @include('fleet.vehicles.partials.errors')

        <form method="POST" action="{{ route('fleet.vehicles.update', $vehicle) }}" class="rounded-card bg-surface p-5 shadow-card sm:p-6">
            @csrf
            @method('PUT')

            @include('fleet.vehicles.partials.form')

            <div class="mt-6 flex flex-wrap justify-end gap-2.5 border-t border-line pt-5">
                <x-secondary-button-a href="{{ route('fleet.vehicles') }}">Cancelar</x-secondary-button-a>
                <x-primary-button><x-icon name="check" /> Salvar</x-primary-button>
            </div>
        </form>
    </x-page>
</x-app-layout>
