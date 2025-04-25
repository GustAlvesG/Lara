<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $company->name }}
        </h2>
    </x-slot>

    <x-slot name="css">
        <link rel="stylesheet" href="{{ asset('css/company/card.css') }}">
    </x-slot>

    <x-block>
        <x-slot name="content">
            @include('company.partials.company-data', ['company' => $company])
        </x-slot>
    </x-block>

    <x-block>
        <x-slot name="content">
            @include('company.partials.company-rules', ['rules' => $rules, 'company_id' => $company->id])
        </x-slot>
    </x-block>
    
    <x-block>
        <x-slot name="content">
            {{-- @include('company.partials.company-rules', ['rules' => $rules, 'company_id' => $company->id]) --}}
            @include('company.partials.company-outers', ['outers' => $outers, 'company_id' => $company->id])
        </x-slot>
    </x-block>

    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
    </x-slot>
</x-app-layout>
