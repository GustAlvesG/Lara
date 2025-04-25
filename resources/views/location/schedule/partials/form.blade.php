
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
                <input class="form-check mx-2 places-checkbox"  type="checkbox" name="places[]" value="{{ $place->id }}" checked>
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
            <x-text-input type="text" name="start_date" id="start_date" class="form-control timer " value="Regra ##"/>
        </div>
        <div class="col-5">
            <label class="form-check text-gray-900 dark:text-gray-100" for="type">
                Tipo de Regra
            </label>
            <select id="type-select" name="type" class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full">
                <option value="include" selected >Inclusão</option>
                <option value="exclude">Exclusão</option>
            </select>
        </div>
    </div>
</div>

<hr>

<div class="form-check py-4">
    <div class="row">
        <div class="col-3">
            <input class="form-check rule-checkbox" type="checkbox" name="period" id="period">
            <label class="form-check text-gray-900 dark:text-gray-100" for="period">
                Período
            </label>
        </div>
        <div class="col-9 hidden onCheck" id="period_range">
            <div class="row">
                <div class="col">
                    <x-input-label class="period-range" for="start_date">Período Início</x-input-label>
                    <x-text-input type="date" name="start_date" id="start_date" class="form-control timer "/>
                </div>
                <div class="col">
                    <x-input-label class="period-range" for="end_date">Período Limite</x-input-label>
                    <x-text-input type="date" name="end_date" id="end_date" class="form-control timer "/>
                </div>
            </div>
        </div>
    </div>
</div>
<hr>

<div class="row">
    <div class="col-3">
        <div class="form-check py-4">
        <input class="form-check rule-checkbox" type="checkbox" name="weekdays" id="weekdays">
        <label class="form-check text-gray-900 dark:text-gray-100" for="weekdays">
            Dias da Semana
        </label>
        </div>
    </div>       
    <div class="col-9  hidden onCheck">
        <div class="form-check  py-4" id="weekdays">
            <input class="form-check week-day-range mx-2" data-pt-day="domingo" type="checkbox" name="weekdays[]" id="sunday" value="Dom">
            <label class="form-check text-gray-900 dark:text-gray-100" for="sunday">
                Dom
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="segunda-feira"  type="checkbox" name="weekdays[]" id="monday" value="Seg">
            <label class="form-check text-gray-900 dark:text-gray-100" for="monday">
                Seg
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="terça-feira" type="checkbox" name="weekdays[]" id="tuesday" value="Ter">
            <label class="form-check text-gray-900 dark:text-gray-100" for="tuesday">
                Ter
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="quarta-feira" type="checkbox" name="weekdays[]" id="wednesday" value="Qua">
            <label class="form-check text-gray-900 dark:text-gray-100" for="wednesday">
                Qua
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="quinta-feira" type="checkbox" name="weekdays[]" id="thursday" value="Qui">
            <label class="form-check text-gray-900 dark:text-gray-100" for="thursday">
                Qui
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="sexta-feira" type="checkbox" name="weekdays[]" id="friday" value="Sex">
            <label class="form-check text-gray-900 dark:text-gray-100" for="friday">
                Sex
            </label>
            <input class="form-check week-day-range mx-2" data-pt-day="sábado" type="checkbox" name="weekdays[]" id="saturday" value="Sab">
            <label class="form-check text-gray-900 dark:text-gray-100" for="saturday">
                Sab
            </label>
        </div>
    </div>
</div>


<hr>

<div class="form-check py-4">
    <div class="row">
        <div class="col-3"> 
            <input class="form-check rule-checkbox" type="checkbox" name="time" id="time">
            <label class="form-check text-gray-900 dark:text-gray-100" for="time">
                Horário 
            </label>
        </div>
        <div class="col-9 hidden onCheck" id="time_range">
            <div class="row">
                <div class="col">
                    <x-input-label class="time-range" for="start_time">Horário Início</x-input-label>
                    <x-text-input type="time" name="start_time"  class="form-control timer " />
                </div>
                <div class="col">
                    <x-input-label class="time-range" for="end_time">Horário Limite</x-input-label>
                    <x-text-input type="time" name="end_time"  class="form-control timer " />
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
                <input class="form-check rule-checkbox" type="checkbox" name="time" id="time">
                <label class="form-check text-gray-900 dark:text-gray-100" for="time">
                    Duração 
                </label>
            </div>
            <div class="col-3 hidden onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="duration">Duração</x-input-label>
                        <x-text-input type="time" name="duration"  class="form-control timer " />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="time" id="time">
                <label class="form-check text-gray-900 dark:text-gray-100" for="time">
                    Intervalo 
                </label>
            </div>
            <div class="col-3 hidden onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="interval">Intervalo</x-input-label>
                        <x-text-input type="time" name="interval"  class="form-control timer " />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="time" id="time">
                <label class="form-check text-gray-900 dark:text-gray-100" for="time">
                    Antecedência
                </label>
            </div>
            <div class="col-3 hidden onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="antecedence">Antecedência (em dias)</x-input-label>
                        <x-text-input type="number" name="antecedence"  class="form-control timer" step="1" min="0" />
                    </div>
                </div>
            </div>
        </div>
    </div>
    <hr>
    <div class="form-check py-4">
        <div class="row">
            <div class="col-3"> 
                <input class="form-check rule-checkbox" type="checkbox" name="time" id="time">
                <label class="form-check text-gray-900 dark:text-gray-100" for="time">
                    Limite de Reserva 
                </label>
            </div>
            <div class="col-3 hidden onCheck" id="time_range">
                <div class="row">
                    <div class="col">
                        <x-input-label class="time-range" for="quantity">Limite de Reserva</x-input-label>
                        <x-text-input type="time" name="quantity"  class="form-control timer " />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>