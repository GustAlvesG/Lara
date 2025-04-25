<x-base-element>
    <x-slot name="image">
        {{ $item->image ? asset('images/'. $item->image) : asset('images/defaultImage.jpg') }}
    </x-slot>

    <x-slot name="bodyElement">
        <a href="{{ route('information.show', $item->id) }}" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $item->category ? $item->category . ': ' : null }} {{ $item->name }} {{ $item->responsible ? ' - ' . $item->responsible : null }}
        </a>
        <div class="row">
            <div class="col-6">
                <span class="date">Atualizado em {{ $item->created_at->format('d/m/Y') }} por {{ $item->user->name }}</span>
            </div>
        </div>
        {{-- Description --}}
        <div class="row my-2">
            <div class="col-12">
                <p class="description italic" style="word-wrap: break-word; overflow-wrap:">{!! $item->description !!}</p>
            </div>
        </div>

        <div class="row">
            @if ($item->slots)
                <div class="col-4">
                    <strong class="slots">Vagas disponíveis:</strong> {{ $item->slots }}
                </div>  
            @endif
        </div>
        
        @if (count($item->prices))
        <strong class="price">Preços:</strong> 
        @foreach ($item->prices as $itemPrice)
        <div class="row">
            <div class="col">
                <span class="price">{{ $itemPrice }}</span>
            </div>
        </div>
        @endforeach
        @endif 
    </x-slot>

    <x-slot name="showMoreRoute">
        {{ route('information.show', $item->id) }}
    </x-slot>

    
</x-base-element>