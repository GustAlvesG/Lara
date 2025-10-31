


<input type="hidden" name="place_group_id" value="{{ $group->id }}">

<div class="row">
    <div class="col-4">
        <div class="form-check py-4">
            <input class="form-check" type="checkbox" name="status" id="status" value="1" checked>
            <label class="form-check text-gray-900 dark:text-gray-100" for="status">
                Habilitar Regra
            </label>
        </div>
    </div>
    <div class="col-8">
        <div class="form-check py-4 d-grid grid-template-columns-3">
            <div class="places">
                <input class="form-check mx-2 places-checkbox-all" type="checkbox" name="places[]" value="all">
                <label class="form-check text-gray-900 dark:text-gray-100" for="places">Todas</label>
            </div>
            @foreach ($group->places as $place)
                <div class="places">
                    <input class="form-check mx-2 places-checkbox" type="checkbox" name="places[]" value="{{ $place->id }}"
                        @if (isset($rule) && $rule->places && $rule->places->pluck('id')->contains($place->id)) checked
                        @elseif (!isset($rule)) checked
                        @endif>
                    <label class="form-check text-gray-900 dark:text-gray-100" for="places">{{ $place->name }}</label>
                </div>
            @endforeach
        </div>
    </div>
</div>
<hr>

<div class="form-check py-4">
    <div class="row">
        <div class="col-5">
            <label class="form-check text-gray-900 dark:text-gray-100" for="name">Nome da Regra</label>
            <x-text-input type="text" name="name" id="name" class="form-control timer" value="{{ $rule->name ?? '' }}" />
        </div>
        <div class="col-5">
            <label class="form-check text-gray-900 dark:text-gray-100" for="type">Tipo de Regra</label>
            <select id="type-select" name="type" class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full">
                <option value="include" @if (isset($rule) && $rule->type == 'include') selected @endif>Inclusão</option>
                <option value="exclude" @if (isset($rule) && $rule->type == 'exclude') selected @endif>Exclusão</option>
            </select>
        </div>
    </div>
</div>

<hr>

{{-- Período --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'period',
    'checkboxId' => 'period',
    'checked' => isset($rule) && ($rule->start_date || $rule->end_date),
    'label' => 'Período',
    'blockId' => 'period_range',
    'fields' => [
        [
            'label' => 'Data Início',
            'type' => 'date',
            'name' => 'start_date',
            'value' => $rule->start_date ?? null,
        ],
        [
            'label' => 'Data Limite',
            'type' => 'date',
            'name' => 'end_date',
            'value' => $rule->end_date ?? null,
        ],
    ]
])

{{-- Dias da Semana --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'weekdays',
    'checkboxId' => 'weekdays',
    'checked' => isset($rule) && $rule->weekdays && $rule->weekdays->isNotEmpty(),
    'label' => 'Dias da Semana',
    'blockId' => 'weekdays_range',
    'fields' => [
        [
            'custom' => true,
            'view' => 'partials.weekday-input',
            'data' => ['weekdays' => $rule->weekdays ?? null]
        ]
    ]
])

{{-- Horário --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'hour_front',
    'checkboxId' => 'hour_front',
    'checked' => isset($rule) && ($rule->start_time || $rule->end_time),
    'label' => 'Horário',
    'blockId' => 'time_range',
    'fields' => [
        [
            'label' => 'Horário Início',
            'type' => 'time',
            'name' => 'start_time',
            'value' => $rule->start_time ?? null,
        ],
        [
            'label' => 'Horário Limite',
            'type' => 'time',
            'name' => 'end_time',
            'value' => $rule->end_time ?? null,
        ],
    ]
])

{{-- Duração --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'duration_front',
    'checkboxId' => 'duration_front',
    'checked' => isset($rule) && $rule->duration,
    'label' => 'Duração',
    'blockId' => 'duration_range',
    'fields' => [
        [
            'label' => 'Duração',
            'type' => 'time',
            'name' => 'duration',
            'value' => $rule->duration ?? null,
        ]
    ]
])

{{-- Intervalo --}}
{{-- @include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'interval_front',
    'checkboxId' => 'interval_front',
    'checked' => isset($rule) && $rule->interval,
    'label' => 'Intervalo',
    'blockId' => 'interval_range',
    'fields' => [
        [
            'label' => 'Intervalo',
            'type' => 'time',
            'name' => 'interval',
            'value' => $rule->interval ?? null,
        ]
    ]
]) --}}

{{-- Antecedência --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'minimium_antecedence_front',
    'checkboxId' => 'minimium_antecedence_front',
    'checked' => isset($rule) && $rule->minimium_antecedence,
    'label' => 'Antecedência Mínima',
    'blockId' => 'minimium_antecedence_range',
    'fields' => [
        [
            'label' => 'Antecedência Mínima (em dias)',
            'type' => 'number',
            'name' => 'minimium_antecedence',
            'value' => $rule->minimium_antecedence ?? null,
            'attributes' => 'step="1" min="0"'
        ]
    ]
])
{{-- Antecedência --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'maximium_antecedence_front',
    'checkboxId' => 'maximium_antecedence_front',
    'checked' => isset($rule) && $rule->maximium_antecedence,
    'label' => 'Antecedência Máxima',
    'blockId' => 'maximium_antecedence_range',
    'fields' => [
        [
            'label' => 'Antecedência Máxima(em dias)',
            'type' => 'number',
            'name' => 'maximium_antecedence',
            'value' => $rule->maximium_antecedence ?? null,
            'attributes' => 'step="1" min="0"'
        ]
    ]
])

{{-- Limite de Reserva --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'limit_front',
    'checkboxId' => 'limit_front',
    'checked' => isset($rule) && $rule->quantity,
    'label' => 'Limite de Reserva',
    'blockId' => 'limit_range',
    'fields' => [
        [
            'label' => 'Limite de Reserva',
            'type' => 'number',
            'name' => 'quantity',
            'value' => $rule->quantity ?? null,
            'attributes' => 'min="1" step="1"'
        ]
    ]
])


{{-- 
<input type="hidden" name="place_group_id" value="{{ $group->id }}">

<div class="row">
    <div class="col-4">
        <div class="form-check py-4">
            <input class="form-check" type="checkbox" name="status" id="status" value="1" checked>
            <label class="form-check text-gray-900 dark:text-gray-100" for="status">
                Habilitar Regra
            </label>
        </div>
    </div>
    <div class="col-8">
        <div class="form-check py-4 d-grid grid-template-columns-3">
            <div class="places">
                <input class="form-check mx-2 places-checkbox-all"  type="checkbox" name="places[]" value="all">
                <label class="form-check text-gray-900 dark:text-gray-100" for="places">
                    Todas
                </label>
            </div>
            @foreach ($group->places as $place)
            <div class="places">
                <input class="form-check mx-2 places-checkbox"  type="checkbox" name="places[]" value="{{ $place->id }}" 
                
                @if (isset($rule) && in_array($place->id, $rule->places->pluck('id')->toArray()))
                    checked
                @elseif (!@isset($rule))
                    checked
                @endif>

                <label class="form-check text-gray-900 dark:text-gray-100" for="places">
                    {{ $place->name }}
                </label>
            </div>
            @endforeach
        </div>
    </div>
</div>
<hr>
<div class="form-check py-4">
    <div class="row">
        <div class="col-5">
            <label class="form-check text-gray-900 dark:text-gray-100" for="type">
                Nome da Regra
            </label>
            <x-text-input type="text" name="start_date" id="start_date" class="form-control timer " value="{{ $rule->name ?? null }}" />
        </div>
        <div class="col-5">
            <label class="form-check text-gray-900 dark:text-gray-100" for="type">
                Tipo de Regra
            </label>
            <select id="type-select" name="type" class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full">
                <option value="include"
                @if (isset($rule) && $rule->type == 'include') selected @endif>
                    Inclusão
                </option>
                <option value="exclude"
                 @if (isset($rule) && $rule->type == 'exclude') selected @endif>
                    Exclusão
                </option>
            </select>
        </div>
    </div>
</div>

<hr>

<div class="form-check py-4">
    <div class="row">
        <div class="col-3">
            <input class="form-check rule-checkbox" type="checkbox" name="period" id="period"
            @if (isset($rule) && $rule->start_date || $rule->end_date) 
                checked           
            @endif>
            <label class="form-check text-gray-900 dark:text-gray-100" for="period">
                Período
            </label>
        </div>
        <div class="col-9

        @if (isset($rule) && $rule->start_date || $rule->end_date) 
        @else hidden
        @endif
        
        onCheck" id="period_range">
            <div class="row">
                <div class="col">
                    <x-input-label class="period-range" for="start_date">Período Início</x-input-label>
                    <x-text-input type="date" name="start_date" id="start_date" class="form-control timer " value="{{ $rule->start_date ?? null}}"/>
                </div>
                <div class="col">
                    <x-input-label class="period-range" for="end_date">Período Limite</x-input-label>
                    <x-text-input type="date" name="end_date" id="end_date" class="form-control timer " value="{{ $rule->end_date ?? null}}"/>
                </div>
            </div>
        </div>
    </div>
</div>
<hr>

<div class="row">
    <div class="col-3">
        <div class="form-check py-4">
        <input class="form-check rule-checkbox" type="checkbox" name="weekdays" id="weekdays"
        @if (isset($rule) && $rule->weekdays->isNotEmpty()) 
            checked
        @endif>
        <label class="form-check text-gray-900 dark:text-gray-100" for="weekdays">
            Dias da Semana
        </label>
        </div>
    </div>       
    <div class="col-9  
    @if (isset($rule) && $rule->weekdays->isNotEmpty()) 
    @else hidden
    @endif
    onCheck">
        <div class="form-check  py-4" id="weekdays">
            @include('partials.weekday-input', [
                'weekdays' => $rule->weekdays ?? null
            ])
        </div>
    </div>
</div>


<hr>

<div class="form-check py-4">
    <div class="row">
        <div class="col-3"> 
            <input class="form-check rule-checkbox" type="checkbox" name="hour_front" id="hour_front"
            @if (isset($rule) && $rule->start_time || $rule->end_time) 
                checked
            @endif>
            <label class="form-check text-gray-900 dark:text-gray-100" for="hour_front">
                Horário 
            </label>
        </div>
        <div class="col-9

        @if (isset($rule) && $rule->start_time || $rule->end_time)
        @else
            hidden
        @endif>
        
        onCheck" id="time_range">
            <div class="row">
                <div class="col">
                    <x-input-label class="time-range" for="start_time">Horário Início</x-input-label>
                    <x-text-input type="time" name="start_time"  class="form-control timer " 
                    value="{{ $rule->start_time ?? null }}"
                    />
                </div>
                <div class="col">
                    <x-input-label class="time-range" for="end_time">Horário Limite</x-input-label>
                    <x-text-input type="time" name="end_time"  class="form-control timer " 
                    value="{{ $rule->end_time ?? null }}"
                    />
                </div>
            </div>
        </div>
    </div>
</div>

<div class="only-include">
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="duration_front" id="duration_front"
                @if (isset($rule) && $rule->duration) 
                    checked
                @endif>
                <label class="form-check text-gray-900 dark:text-gray-100" for="duration_front">
                    Duração 
                </label>
            </div>
            <div class="col-3 
            @if (isset($rule) && $rule->duration)
            @else
                hidden
            @endif
            onCheck" id="duration_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="duration">Duração</x-input-label>
                        <x-text-input type="time" name="duration"  class="form-control timer "
                        value="{{ $rule->duration ?? null }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="interval_front" id="interval_front"
                @if (isset($rule) && $rule->interval) 
                    checked
                @endif>
                <label class="form-check text-gray-900 dark:text-gray-100" for="interval_front">
                    Intervalo 
                </label>
            </div>
            <div class="col-3
            @if (isset($rule) && $rule->interval)
            @else
                hidden
            @endif
            onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="interval">Intervalo</x-input-label>
                        <x-text-input type="time" name="interval"  class="form-control timer" value="{{ $rule->interval ?? null }}"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="antecedente_front" id="antecedente_front"
                @if (isset($rule) && $rule->antecedence) 
                    checked
                @endif>
                <label class="form-check text-gray-900 dark:text-gray-100" for="antecedente_front">
                    Antecedência
                </label>
            </div>
            <div class="col-3 

            @if(isset($rule) && $rule->antecedence)
            @else
                hidden
            @endif
            
            onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="antecedence">Antecedência (em dias)</x-input-label>
                        <x-text-input type="number" name="antecedence"  class="form-control timer" step="1" min="0" 
                        value="{{ $rule->antecedence ?? null }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="limit_front" id="limit_front"
                @if (isset($rule) && $rule->quantity) 
                    checked
                @endif>
                <label class="form-check text-gray-900 dark:text-gray-100" for="limit_front">
                    Limite de Reserva 
                </label>
            </div>
            <div class="col-3 

            @if (isset($rule) && $rule->quantity)
            @else
                hidden
            @endif
            
            onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="quantity">Limite de Reserva</x-input-label>
                        <x-text-input type="number" name="quantity" min="1" step="1"  class="form-control" 
                        value="{{ $rule->quantity ?? null }}"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> --}}
