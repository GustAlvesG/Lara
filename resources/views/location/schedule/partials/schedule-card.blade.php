<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->
<div class="rule-card border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">

    {{-- <div class="mb-2 align-items-center">
        <img src="{{ asset('images/'. $item->image) }} " alt="">
    </div> --}}

    <a href="{{ route('place-group.editPlace', $item->id) }}" class=" font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center justify-content-center d-flex">
        Agendamento #{{ $item->id }}
    </a>

    <div class="mb-2">
        <strong>Quadra:</strong>
        <span>{{ $item->place->name }}</span>
    </div>
    <div class="mb-2">
        <strong>Horário:</strong>
        <span>{{ \Carbon\Carbon::parse($item->start_schedule)->format('H:i') }} - {{ \Carbon\Carbon::parse($item->end_schedule)->format('H:i') }}</span>
    </div>
{{-- 
    <div class="mb-2">
        <strong>Status:</strong>
        <span>{{ $item->status }}</span>
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

    </div> --}}
</div>