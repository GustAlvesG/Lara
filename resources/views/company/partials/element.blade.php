<x-base-element>
    <x-slot name="image">
        {{ $item->image ? asset('images/'. $item->image) : asset('images/defaultImage.jpg') }}
    </x-slot>

    <x-slot name="bodyElement">
        <a href="{{ route('company.show', $item->id) }}" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $item->name }}
        </a>
    </x-slot>

    <x-slot name="showMoreText">
        {{ __('Ver detalhes') }}
    </x-slot>

    <x-slot name="showMoreRoute">
        {{ route('company.show', $item->id) }}
    </x-slot>

    
</x-base-element>