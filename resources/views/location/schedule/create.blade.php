<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Projeto 2XKO - Alocações
            </h2>
        </div>
       </div>
    </x-slot>

    <x-slot name="css">
       
    </x-slot>

    <x-block>
        <x-slot name="content">
            @include('location.schedule.partials.form-create')
        </x-slot>
    </x-block>

   

    
   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
        <script src="{{ asset('js/pagination.js') }}"></script>
        <script src="{{ asset('js/accordion.js') }}"></script>
        
    </x-slot>

    
</x-app-layout>
