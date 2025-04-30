<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->
<div class="rule-card border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">

    <div class="mb-2 align-items-center">
        <img src="{{ asset('images/'. $place->image) }} " alt="">
    </div>

    <h2 class="font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center">
        {{ $place->name }}
    </h2>



    <div class="mb-2">
        <strong>Tipo:</strong>
        <span>{{ $place->status  ? "Ativo" : "Inativo" }}</span>
    </div>


    {{-- <div class="mb-2">
        <div class="row">

            <div class="col">
                <x-secondary-button-a href="{{ route('accessrule.ruleByID', $rule->id) }}">
                    Editar
                </x-secondary-button-a>
            </div>
            <div class="col">
                <form action="{{ route('accessrule.destroy', $rule->id) }}" method="POST" >
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