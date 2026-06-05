<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Parceiros Terceirizados - Editar') }}
        </h2>
    </x-slot>

    <x-slot name="css">
    </x-slot>

    <div>
        <x-crud.form :formRoute="route('company.update', $company->id)" :formMethod="'POST'" enctype="multipart/form-data" :hasImageSection="true" :existingImageUrl="$company->image ? asset('images/' . $company->image) : null">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Editar Parceiro Terceirizado</h1>
                        <p class="text-gray-500 font-medium">Atualize os dados do parceiro terceirizado abaixo.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">
                @method('PUT')
                @include('companies.partials.form', ['model' => $company])
            </x-slot>

        </x-crud.form>
    </div>

    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
