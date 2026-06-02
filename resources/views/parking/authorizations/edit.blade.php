<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar Placa — {{ $authorization->plate }}
        </h2>
    </x-slot>

    <x-slot name="css"></x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8 max-w-2xl">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">

                <form method="POST" action="{{ route('parking-authorizations.update', $authorization) }}">
                    @csrf
                    @method('PUT')

                    @include('parking.authorizations.partials.form', ['item' => $authorization])

                    <div class="flex items-center justify-end gap-4 mt-6">
                        <a href="{{ route('parking-authorizations.index') }}"
                           class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                            Cancelar
                        </a>
                        <x-primary-button>
                            Atualizar
                        </x-primary-button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <x-slot name="js"></x-slot>
</x-app-layout>
