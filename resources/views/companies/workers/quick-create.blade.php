<x-app-layout>

    <x-slot name="css">
    </x-slot>

    <div>
        <x-crud.form :formRoute="route('company.worker.quick.store')" :formMethod="'POST'" enctype="multipart/form-data" :hasImageSection="false">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <a href="{{ route('company.index') }}"
                       class="p-2 bg-white dark:bg-gray-700 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-600 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Cadastro Rápido de Funcionário</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Selecione a empresa e preencha os dados do funcionário.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">

                @include('companies.workers.partials.form', ['companies' => $companies, 'selectedCompanyId' => $selectedCompanyId])

            </x-slot>

        </x-crud.form>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
