    {{-- <x-base-element>
        <x-slot name="image"> --}}
        {{-- {{ $item->image_horizontal ? asset('images/'. $item->image_horizontal) : asset('images/defaultImage.jpg') }} --}}
    {{-- </x-slot>



    <x-slot name="bodyElement">
        <a href="" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            #{{ $item->id }} - {{ $item->place->name }} - {{ \Carbon\Carbon::parse($item->start_schedule)->format('H:i') }}
        </a>

    </x-slot>

    <x-slot name="showMoreText">
        {{ __('Ver Grupo') }}
    </x-slot>

    <x-slot name="showMoreRoute">
        {{ route('place-group.show', $item->id) }}
    </x-slot>

    
</x-base-element> --}}


<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->
<div class=" element rule-card border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">

    <div class="mb-2 align-items-center">
        {{-- <img src="{{ asset('images/'. $place->image) }} " alt=""> --}}
    </div>

    <a href="{{ route('place-group.editPlace', $place->id) }}" class=" font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center justify-content-center d-flex">
        {{ $item->name }}
    </a>



    <div class="mb-2">
        <strong>Tipo:</strong>
        <span>{{ $place->status  ? "Ativo" : "Inativo" }}</span>
    </div>

    <div class="mb-2">
        <strong>Valor:</strong>
        <span>R$ {{ number_format($place->price, 2, ',', '.') }}</span>
    </div>

    <div class="mb-2">
        <strong>Disponível:</strong>
        <span>{{ $place->description }}</span>
    </div>


    <div class="mb-2">
        <div class="row">
            <div class="col-6">
                <x-secondary-button-a href="{{ route('place-group.editPlace', $place->id) }}">
                    Editar
                </x-secondary-button-a>
            </div>
            <div class="col-6">
                <form action="{{ route('place-group.destroyPlace', $place->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <x-danger-button onclick="return confirm('{{ 'Tem certeza que deseja deletar?' }}')">
                        {{  __('Excluir') }}
                    </x-danger-button>
                </form>
            </div>
        </div>
    </div>
</div>