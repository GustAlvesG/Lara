<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Externos - Novo') }}

        </h2>

    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div>
        <!-- Header -->
           
        <x-crud.form :formRoute="route('company.store')" :formMethod="'POST'" enctype="multipart/form-data" :hasImageSection="true">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-ink leading-tight">Cadastrar Parceiro Terceirizado</h1>
                        <p class="text-ink-2 font-medium">Preencha os dados do parceiro terceirizado abaixo.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">

                 @include('companies.partials.form')
                
            </x-slot>

        </x-crud.form>

    </div>

    
    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
