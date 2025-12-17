


<input type="hidden" name="place_group_id" value="{{ $group->id }}">

<div class="row">
    <div class="col-4">
        <div class="form-check py-4">
            <input type="hidden" name="status_id" value="2">
            <input class="form-check" type="checkbox" name="status_id" id="status_id" value="1" 
            @if (isset($rule) && $rule->status_id == 1) 
                checked
            @elseif (!isset($rule))
                checked
            @endif>
            <label class="form-check text-gray-900 dark:text-gray-100" for="status_id">
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

<div class="only-include">

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




{{-- Antecedência --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'minimum_antecedence_front',
    'checkboxId' => 'minimum_antecedence_front',
    'checked' => isset($rule) && $rule->minimum_antecedence,
    'label' => 'Antecedência Mínima',
    'blockId' => 'minimum_antecedence_range',
    'fields' => [
        [
            'label' => 'Antecedência Mínima (em dias)',
            'type' => 'number',
            'name' => 'minimum_antecedence',
            'value' => $rule->minimum_antecedence ?? null,
            'attributes' => 'step="1" min="0"'
        ]
    ]
])
{{-- Antecedência --}}
@include('location.schedule.partials.toggle-block', [
    'checkboxName' => 'maximum_antecedence_front',
    'checkboxId' => 'maximum_antecedence_front',
    'checked' => isset($rule) && $rule->maximum_antecedence,
    'label' => 'Antecedência Máxima',
    'blockId' => 'maximum_antecedence_range',
    'fields' => [
        [
            'label' => 'Antecedência Máxima(em dias)',
            'type' => 'number',
            'name' => 'maximum_antecedence',
            'value' => $rule->maximum_antecedence ?? null,
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


</div>