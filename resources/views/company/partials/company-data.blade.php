<div class="row">
    <x-base-element>
        <x-slot name="image">
            {{ $company->image ? asset('images/'. $company->image) : asset('images/defaultImage.jpg') }}
        </x-slot>
        <x-slot name="bodyElement">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $company->name }}
            </h2>
            {{-- Company --}}
            
        </x-slot>


        <x-slot name="form">
            <x-slot name="formRoute">
                {{ route('company.change', $company->id) }}
            </x-slot>
            <x-slot name="formMethod">
                POST
            </x-slot>
            <x-primary-button type="submit">
                {{ $company->status ? 'Desativar' : 'Ativar' }}
            </x-primary-button>
        </x-slot>

        <form action=" " method="post">

            @csrf
            @method('put')

            <x-slot name="showMoreText">
                {{ $company->status ? 'Desativar' : 'Ativar' }}
            </x-slot>
        </form>
    </x-base-element>
</div>