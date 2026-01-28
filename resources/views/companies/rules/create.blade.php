<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Parceiros Terceirizados - Novo') }}

        </h2>

    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div>
        <!-- Header -->
           
        <x-crud.form :formRoute="route('company.rules.store', $company)" :formMethod="'POST'" :hasImageSection="false">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Nova Regra de Acesso</h1>
                        <p class="text-gray-500 font-medium">Configure as condições de entrada e permanência no local.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">

                 @include('companies.rules.partials.form')
                
            </x-slot>

        </x-crud.form>

    </div>

    
    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
