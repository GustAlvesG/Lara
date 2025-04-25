<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Editar Regra de Acesso - {{ $rule->company->name }}
        </h2>
    </x-slot>

    <x-slot name="css">
        
    </x-slot>

    <x-block>
        <x-slot name="content">
            <form method="POST" action="{{ route('accessrule.update', $rule->id) }}">
                @csrf
                @method('PUT')
                {{-- <input type="hidden" name="company_id" value="{{ $rule->company_id }}"> --}}
                <div class="row">
                    <div class="form-check py-4">
                    <input class="form-check rule-checkbox" value='1' type="checkbox" name="status" id="status" {{ $rule->status ? 'checked' : '' }}>
                    <label class="form-check text-gray-900 dark:text-gray-100" for="status">
                        Habilitar Regra
                    </label>
                    </div>
                </div>
                <div class="row">
                    <div class="form-check py-4">
                        <label class="form-check text-gray-900 dark:text-gray-100" for="type">
                            Tipo de Regra
                        </label>
                        <select name="type" class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full">
                            <option value="include" selected >Inclusão</option>
                            <option value="exclude">Exclusão</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-4">
                        <div class="row">
                            <div class="form-check py-4">
                                <x-input-label class="period-range" for="start_date">Período Início</x-input-label>
                                <x-text-input type="date" name="start_date" id="start_date" class="form-control timer " value="{{ $rule->start_date ?? '' }}"/>
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-check py-4">
                                <x-input-label class="period-range" for="end_date">Período Limite</x-input-label>
                                <x-text-input type="date" name="end_date" id="end_date" class="form-control timer " value="{{ $rule->end_date ?? '' }}"/>   
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="row">
                            <div class="form-check py-4">
                                <x-input-label class="time-range" for="start_time">Horário Início</x-input-label>
                                <x-text-input type="time" name="start_time"  class="form-control timer " value="{{ $rule->start_time ?? '' }}" />
                            </div>
                        </div>

                        <div class="row">
                            <div class="form-check py-4">
                                <x-input-label class="time-range" for="end_time">Horário Limite</x-input-label>
                                <x-text-input type="time" name="end_time"  class="form-control timer " value="{{ $rule->end_time ?? '' }}" />
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="row">
                            <div class="form-check py-4" id="week_days">
                                <!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/accessRule/edit.blade.php -->

                                <input class="form-check week-day-range mx-2" data-pt-day="segunda-feira" 
                                type="checkbox" name="week_day[]" id="monday" value="Seg" 
                                {{ in_array('Seg', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="monday">Segunda-feira</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="terça-feira" 
                                type="checkbox" name="week_day[]" id="tuesday" value="Ter" 
                                {{ in_array('Ter', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="tuesday">Terça-feira</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="quarta-feira" 
                                type="checkbox" name="week_day[]" id="wednesday" value="Qua" 
                                {{ in_array('Qua', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="wednesday">Quarta-feira</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="quinta-feira" 
                                type="checkbox" name="week_day[]" id="thursday" value="Qui" 
                                {{ in_array('Qui', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="thursday">Quinta-feira</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="sexta-feira" 
                                type="checkbox" name="week_day[]" id="friday" value="Sex" 
                                {{ in_array('Sex', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="friday">Sexta-feira</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="sábado" 
                                type="checkbox" name="week_day[]" id="saturday" value="Sab" 
                                {{ in_array('Sab', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="saturday">Sábado</label>

                                <input class="form-check week-day-range mx-2" data-pt-day="domingo" 
                                type="checkbox" name="week_day[]" id="sunday" value="Dom" 
                                {{ in_array('Dom', explode(';', $rule->weekdays)) ? 'checked' : '' }}>
                                <label class="form-check-label" for="sunday">Domingo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <x-primary-button class="mt-2" id="submit">Salvar</x-primary-button>
            </form>
        </x-slot>
    </x-block>

    <x-slot name="js">
     
    </x-slot>

</x-app-layout>
