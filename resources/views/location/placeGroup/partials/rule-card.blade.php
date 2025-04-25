<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->
<div class="rule-card border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">
    <h2 class="font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center">
        Regra {{ $loop->iteration }}
    </h2>


    <div class="mb-2">
        <strong>Tipo:</strong>
        <span>{{ $rule->type == 'include' ? "Inclusão" : "Exclusão" }}</span>
    </div>

    <div class="mb-2">
        <strong>Status:</strong>
        <span>{{ $rule->status ? 'Ativo' : 'Inativo' }}</span>
    </div>

    <div class="mb-2">
        <strong>Espaços:</strong><br>
        @foreach ($rule->places as $place)
            <span>{{ $place }};</span>
            <br>
        @endforeach
    </div>


    {{-- 
    <div class="mb-2">
        <strong>Período:</strong>
        @if ($rule->start_date == $rule->end_date and $rule->start_date == null)
            <span>Sem período definido</span>
                
        @else
            <span>{{ $rule->start_date }} - {{ $rule->end_date }}</span>
        @endif
    </div>
    
    <div class="mb-2">
        <strong>Horário:</strong>
        @if ($rule->start_time == $rule->end_time and $rule->start_time == null)
            <span>Sem horário definido</span>
        @else
            <span>{{ $rule->start_time }} - {{ $rule->end_time }}</span>
        @endif
    </div>
    
    <div class="mb-2">
        <strong>Dias da Semana:</strong>
        <span>{{ $rule->weekdays }}</span>
    </div>

    <div class="mb-2">
        <strong>Acesso agora:</strong>
        <span>{{ $rule->applicable ? "Liberado" : "Bloqueado" }}</span>
    </div> --}}

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