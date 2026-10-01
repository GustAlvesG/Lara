<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Identificação de veículos">
            Consulte os acessos registrados pelas câmeras de leitura de placas.

            @can(\App\Authorization\Permissions::SIV_PLACAS_DIRETORIA)
                <x-slot:actions>
                    <x-secondary-button-a href="{{ route('parking-authorizations.index') }}">
                        <x-icon name="doc" /> Placas Diretoria
                    </x-secondary-button-a>
                </x-slot:actions>
            @endcan
        </x-page-title>

        @include('parking.partials.dashTotals')

        @include('parking.partials.form')
    </x-page>
</x-app-layout>
