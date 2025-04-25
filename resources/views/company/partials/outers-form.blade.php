{{-- Checkbox + text --}}
<div class="row">
    <div class="form-check py-4">
    <input class="form-check {{ Route::currentRouteName() == 'accessrule.create' ? '' : 'rule-checkbox' }}" type="checkbox" name="status" id="status" value="1" checked>
    <label class="form-check text-gray-900 dark:text-gray-100" for="status">
        Habilitar Regra
    </label>
    </div>
</div>

<div id="rules" class="{{ Route::currentRouteName() == 'accessrule.create' ? '' : 'hidden' }}">

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
    
    <hr>

    <div class="row">
        <div class="col-3">
            <div class="form-check py-4">
            <input class="form-check rule-checkbox" type="checkbox" name="week_day" id="week_day">
            <label class="form-check text-gray-900 dark:text-gray-100" for="week_day">
                Dias da Semana
            </label>
            </div>
        </div>       
        <div class="col-9  hidden onCheck">
            <div class="form-check  py-4" id="week_days">
                <input class="form-check week-day-range mx-2" data-pt-day="domingo" type="checkbox" name="week_day[]" id="sunday" value="Dom">
                <label class="form-check text-gray-900 dark:text-gray-100" for="sunday">
                    Dom
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="segunda-feira"  type="checkbox" name="week_day[]" id="monday" value="Seg">
                <label class="form-check text-gray-900 dark:text-gray-100" for="monday">
                    Seg
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="terça-feira" type="checkbox" name="week_day[]" id="tuesday" value="Ter">
                <label class="form-check text-gray-900 dark:text-gray-100" for="tuesday">
                    Ter
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="quarta-feira" type="checkbox" name="week_day[]" id="wednesday" value="Qua">
                <label class="form-check text-gray-900 dark:text-gray-100" for="wednesday">
                    Qua
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="quinta-feira" type="checkbox" name="week_day[]" id="thursday" value="Qui">
                <label class="form-check text-gray-900 dark:text-gray-100" for="thursday">
                    Qui
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="sexta-feira" type="checkbox" name="week_day[]" id="friday" value="Sex">
                <label class="form-check text-gray-900 dark:text-gray-100" for="friday">
                    Sex
                </label>
                <input class="form-check week-day-range mx-2" data-pt-day="sábado" type="checkbox" name="week_day[]" id="saturday" value="Sab">
                <label class="form-check text-gray-900 dark:text-gray-100" for="saturday">
                    Sab
                </label>
            </div>
        </div>
    </div>


   


</div>

<hr>

<div class="row py-2">
    <p id="rules-text"></p>
</div>
