<x-app-layout>


    <x-slot name="css">
    </x-slot>

    <div>
        <!-- Header -->
           
        <x-crud.form :formRoute="route('company.worker.store', $companyId)" :formMethod="'POST'" enctype="multipart/form-data" :hasImageSection="true">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Cadastrar Funcionário Terceirizado</h1>
                        <p class="text-gray-500 font-medium">Preencha os dados do funcionário terceirizado abaixo.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">

                 @include('companies.workers.partials.form')
                
            </x-slot>

        </x-crud.form>

    </div>

    
    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
