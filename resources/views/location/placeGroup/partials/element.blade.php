<x-base-element>
    <x-slot name="image">
        {{ $item->image_horizontal ? asset('images/'. $item->image_horizontal) : asset('images/defaultImage.jpg') }}
    </x-slot>



    <x-slot name="bodyElement">
        <a href="{{ route('place-group.edit', $item->id) }}" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $item->name }}
        </a>

        <p class="text-gray-600 dark:text-gray-400">
            Categoria: {{ ucfirst($item->category) }}
        </p>

        <p>
            {{ $item }}
        </p>
    </x-slot>

    <x-slot name="showMoreText">
        {{ __('Ver Grupo') }}
    </x-slot>

    <x-slot name="showMoreRoute">
        {{ route('place-group.show', $item->id) }}
    </x-slot>

    
</x-base-element>