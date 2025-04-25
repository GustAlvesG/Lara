<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Grupos de Espaços
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/information/list.css') }}">
        <link rel="stylesheet" href="{{ asset('css/switch.css') }}">
    </x-slot>

    <x-block>
        <x-slot name="content">
            <x-crud.create
                :route="route('place-group.update', $item->id)"
                :method="'POST'"
                :put="True"
                >
            <x-slot name="formInputs">
                @include('location.placeGroup.partials.form', ['item' => $item])
            </x-slot>
            </x-crud.create>
        </x-slot>
    </x-block>

    <x-block>
        <x-slot name="content">
            @include('location.placeGroup.partials.place-group-rules')
        </x-slot>
    </x-block>
   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
    </x-slot>

    
</x-app-layout>
