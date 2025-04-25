<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            @isset($outer)
                {{ 'Nova Regra de Acesso - ' . $outer->name }} - {{ $company->name }}
            @else
                {{ 'Nova Regra de Acesso - ' . $company->name }}
            @endisset

            
        </h2>
    </x-slot>

    <x-slot name="css">
        
    </x-slot>

    <x-block>
        <x-slot name="content">
            <form method="POST" action="{{ route('accessrule.store') }}">
                @csrf

                <input type="hidden" name="company_id" value="{{ $company->id ?? '' }}">
                <input type="hidden" name="outer_id" value="{{ $outer->id ?? '' }}">
                
                @include('accessRule.form')

                <x-primary-button class="mt-2" id="submit">Salvar</x-primary-button>
            </form>
        </x-slot>
    </x-block>

    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
    </x-slot>

</x-app-layout>
