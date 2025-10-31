<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->
<div class="rule-card border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">
    <h2 class="font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center">
        Regra {{ $rule->name ?? $loop->iteration }}
    </h2>

    <hr class="my-2">

    <div class="row">
        <div class="row">
            <div class="col-6">
                <div class="mb-2">
                    <strong>Tipo:</strong>
                    <span>{{ $rule->type == 'include' ? "Inclusão" : "Exclusão" }}</span>
                </div>

                <div class="mb-2">
                    <strong>Período:</strong><br>
                    @if ($rule->start_date == $rule->end_date and $rule->start_date == null)
                        <span>Sem período definido</span>
                    @else
                        <span>
                            {{ $rule->start_date ? \Carbon\Carbon::parse($rule->start_date)->format('d/m/y') : '' }}
                            -
                            {{ $rule->end_date ? \Carbon\Carbon::parse($rule->end_date)->format('d/m/y') : '' }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="col-6">
                <div class="mb-2">
                    <strong>Status:</strong>
                    <span>{{ $rule->status ? 'Ativo' : 'Inativo' }}</span>
                </div>

                <div class="mb-2">
                    <strong>Horário:</strong><br>
                    {{-- Verifica se o horário de início e fim são iguais e nulos --}}
                    {{-- Se ambos forem nulos, exibe "Sem horário definido" --}}
                    {{-- Caso contrário, formata os horários --}}
                    @if ($rule->start_time == $rule->end_time and $rule->start_time == null)
                        <span>Sem horário definido</span>
                    @else
                        <span>
                            {{ $rule->start_time ? \Carbon\Carbon::parse($rule->start_time)->format('H:i') : '' }}
                            -
                            {{ $rule->end_time ? \Carbon\Carbon::parse($rule->end_time)->format('H:i') : '' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                {{-- Verifica se a coleção de dias da semana não está vazia --}}
                {{-- Se estiver vazia, não exibe nada --}}
                <div class="mb-2">
                    <strong>Dias da Semana:</strong>
                    @if($rule->weekdays->isEmpty())
                        <span>Nenhum dia especificado</span>
                    @else
                        @foreach ($rule->weekdays as $day)
                            {{ ucfirst($day->short_name_pt) }}@if (!$loop->last), @endif
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-6">
                <div class="mb-2">
                    <strong>Duração:</strong>
                    <span>{{ $rule->duration ?? 'Duração não especificada' }}</span>
                </div>

                <div class="mb-2">
                    <strong>Antecedência:</strong>
                    <span>{{ $rule->antecedence ?? 'Antecedência não especificada' }}</span>
                </div>
            </div>
            <div class="col-6">
                <div class="mb-2">
                    <strong>Intervalo:</strong>
                    <span>{{ $rule->interval ?? 'Intervalo não especificado' }}</span>
                </div>

                  <div class="mb-2">
                    <strong>Limite:</strong>
                    <span>{{ $rule->quantity ?? 'Limite não especificado' }}</span>
                </div>
            </div>
        </div>
    </div>

    @isset($checkbox)
        <div class="mb-2">
            <input type="checkbox" name="rules[]" id="rules" value="{{ $rule->id }}" class="form-check-input"
            {{-- Check if place->id is in the rule's places --}}
            @if (isset($rule->places) && isset($place) && collect($rule->places)->contains('id', $place->id))
                checked
            @endif
            >
            <label for="rules" class="form-check-label">Habilitar</label>
        </div>
    @else
        <div class="row">

            <div class="col-3">
                <x-secondary-button-a href="{{ route('place-group.editScheduleRule', $rule->id) }}">
                    Editar
                </x-secondary-button-a>
            </div>
            <div class="col-3">
                <form action="{{ route('place-group.destroyScheduleRule',$rule->id) }}" method="POST" >
                    @csrf
                    @method('DELETE')
                    <x-danger-button onclick="return confirm('{{ 'Tem certeza que deseja deletar?' }}')">
                        {{  __('Excluir') }}
                    </x-danger-button>
                </form>
            </div>
        </div>
    @endisset








    
    {{-- 



    <div class="mb-2">
        <strong>Acesso agora:</strong>
        <span>{{ $rule->applicable ? "Liberado" : "Bloqueado" }}</span>
    </div> --}}

    {{-- <div class="mb-2">
        

    </div> --}}
</div>