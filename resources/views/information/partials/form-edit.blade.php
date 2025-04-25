<form action="{{ $route }}" method="{{ $method ?? 'POST' }}" enctype="multipart/form-data">
    @csrf
    <div class="hidden">
        <x-text-input name="information_id" id="information_id" class="form-control" value="{{ $info->information_id }}"/>
    </div>

    <div class="form-group py-1">
        <x-input-label for="name">Nome</x-input-label>
        <x-text-input name="name" id="name" class="form-control" value="{{ $info->name }}"/>
    </div>
    <div class="form-group py-1">
        <x-input-label for="description">Descrição</x-input-label>
        <textarea name="description" id="description" class="form-control pt-1 description">
            {{ $info->description }}
        </textarea>
    </div>
    <div id="option-area">
        <div class="form-group py-1 image hidden">
            <x-input-label for="image">Imagem</x-input-label>
            {{-- Input Image --}}
            <input type="file" name="image" id="image" class="form-control" />
        </div>


        <div class="form-group py-1 fee {{ $info->fee ? '' : 'hidden' }}" >
            <x-input-label for="fee">Taxa de Matrícula</x-input-label>
            <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="fee" id="fee" class="form-control" value="" />
        </div>

       

        <div id="pricesRow" class="form-group prices {{ count($info->name_price) > 0 || count($info->price_associated) > 0 || count($info->price_not_associated) > 0 ? '' : 'hidden'  }}">
            @foreach ($info->name_price as $index => $value)
                <div class="row py-1 ">
                    <div class="col-4">
                        <x-input-label for="name_price">Título do #{{ $index }}</x-input-label>
                        <x-text-input type="text" name="name_price[]" id="price_socio_{{ $index }}" class="form-control name_price" value="{{ $value }}" />
                    </div>
                    <div class="col-4">
                        <x-input-label for="price_associated">R$ Sócio #{{ $index }}</x-input-label>
                        <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_associated[]" id="price_socio_{{ $index }}" class="form-control price_associated" value="{{ $info->price_associated[$index] ?? '' }}"/>
                    </div>
                    <div class="col-4">
                        <x-input-label for="price_not_associated">R$ Não Sócio #{{ $index }}</x-input-label>
                        <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_not_associated[]" id="price_nao_socio_{{ $index }}" class="form-control price_not_associated" value="{{ $info->price_not_associated[$index] ?? '' }}"/>
                    </div>
                </div>
            @endforeach

            <div class="row py-1 hidden ">
                <div class="col-4">
                    <x-input-label class="count_tag name_price edit" for="name_price"></x-input-label>
                    <x-text-input type="text" name="name_price[]" id="price_socio_" class="form-control name_price" value="" />
                </div>
                <div class="col-4">
                    <x-input-label class="count_tag price_associated " for="price_associated">R$ Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_associated[]" id="price_socio_" class="form-control price_associated" value=""/>
                </div>
                <div class="col-4">
                    <x-input-label class="count_tag price_not_associated " for="price_not_associated">R$ Não Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_not_associated[]" id="price_nao_socio_" class="form-control price_not_associated" value=""/>
                </div>
            </div>
           
        </div>

        <div id="dayHourRow" class="form-group py-1 day_hour {{ implode(";", $info->day_hour) != ";" ? '' : 'hidden' }}" >
            @foreach ($info->day_hour as $item)
                @php 
                    $data = explode(",", $item);
                    $day = $data[0];
                    $start_hour = $data[1];
                    $end_hour = $data[2];
                @endphp
            <div class="row py-1">
                <div class="col-4">
                    <x-input-label class="count_tag day_hour" for="day_hour">Dia</x-input-label>
                    <select class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full" name="day[]" id="">
                        <option  value="#">Selecione uma opção</option>
                        <option {{ $day == 'Dom' ? 'selected' : '' }} value="Dom">Domingo</option>
                        <option {{ $day == '2ª' ? 'selected' : '' }} value="2ª">Segunda-feira</option>
                        <option {{ $day == '3ª' ? 'selected' : '' }} value="3ª">Terça-feira</option>
                        <option {{ $day == '4ª' ? 'selected' : '' }} value="4ª">Quarta-feira</option>
                        <option {{ $day == '5ª' ? 'selected' : '' }} value="5ª">Quinta-feira</option>
                        <option {{ $day == '6ª' ? 'selected' : '' }} value="6ª">Sexta-feira</option>
                        <option {{ $day == 'Sab' ? 'selected' : '' }} value="Sab">Sábado</option>         
                        <option {{ $day == 'Dias de Semana' ? 'selected' : '' }} value="Dias de Semana">Dias de Semana</option>
                        <option {{ $day == 'Fim de Semana' ? 'selected' : '' }} value="Fim de Semana">Fim de Semana</option>
                        <option {{ $day == 'Todos' ? 'selected' : '' }} value="Todos os dias">Todos</option>         
                    </select>
                </div>
                <div class="col-4">
                    <x-input-label class="start_hour count_tag" for="day_hour">Horário Início</x-input-label>
                    <x-text-input type="time" name="start_hour[]"  class="form-control timer " value="00:00" />
                </div>
                <div class="col-4">
                    <x-input-label class="end_hour count_tag" for="day_hour">Horário Fim</x-input-label>
                    <x-text-input type="time" name="end_hour[]" class="form-control timer" value="00:00" />
                </div>
            </div>
            @endforeach
            <div class="row py-1 hidden">
                <div class="col-4">
                    <x-input-label class="count_tag day_hour" for="day_hour">Dia</x-input-label>
                    <select class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full" name="day[]" id="">
                        <option  value="#">Selecione uma opção</option>
                        <option  value="Dom">Domingo</option>
                        <option  value="2ª">Segunda-feira</option>
                        <option  value="3ª">Terça-feira</option>
                        <option  value="4ª">Quarta-feira</option>
                        <option  value="5ª">Quinta-feira</option>
                        <option  value="6ª">Sexta-feira</option>
                        <option  value="Sab">Sábado</option>         
                        <option  value="Dias de Semana">Dias de Semana</option>
                        <option  value="Fim de Semana">Fim de Semana</option>
                        <option  value="Todos os dias">Todos</option>         
                    </select>
                </div>
                <div class="col-4">
                    <x-input-label class="start_hour count_tag" for="day_hour">Horário Início</x-input-label>
                    <x-text-input type="time" name="start_hour[]"  class="form-control timer " value="00:00" />
                </div>
                <div class="col-4">
                    <x-input-label class="end_hour count_tag" for="day_hour">Horário Fim</x-input-label>
                    <x-text-input type="time" name="end_hour[]" class="form-control timer" value="00:00" />
                </div>
            </div>
        </div>



        <div class="form-group py-1 category {{ $info->category ? '' : 'hidden' }}" >
            <x-input-label for="category">Categoria</x-input-label>
            <x-text-input name="category" id="category" class="form-control"  value="{{ $info->category }}" />
        </div>

        
        <div id="responsibleRow" class="form-group responsible border-t-4" >
            @foreach ($info->responsible as $index => $value)
            @if ($value != '' or $info->responsible_contact[$index] != '')
                <div class="row py-1">
                    <div class="col-6">
                        <x-input-label for="responsible_{{ $index }}">Responsável {{ $index + 1 }}</x-input-label>
                        <x-text-input name="responsible[]" id="responsible_{{ $index }}" class="form-control responsible" value="{{ $value }}" />
                    </div>
                    <div class="col-6">
                        <x-input-label for="responsible_contact{{ $index }}">Telefone</x-input-label>
                        <x-text-input name="responsible_contact[]" id="responsible_contact{{ $index }}" class="form-control responsible_contact" value="{{ $info->responsible_contact[$index] ?? '' }}" />
                    </div>
                </div>
            @endif
            @endforeach
            <div class="row py-1 hidden">
                <div class="col-6">
                    <x-input-label for="responsible_">Responsável #</x-input-label>
                    <x-text-input name="responsible[]" id="responsible_" class="form-control responsible" value="" />
                </div>
                <div class="col-6">
                    <x-input-label for="responsible_contact">Telefone #</x-input-label>
                    <x-text-input name="responsible_contact[]" id="responsible_contact" class="form-control responsible_contact" value="" />
                </div>
            </div>
        </div>


        <div class="form-group py-1 slots {{ $info->slots ? '' : 'hidden' }}" >
            <x-input-label for="slots">Número de Vagas</x-input-label>
            <x-text-input type="number" min="0" name="slots" id="slots" class="form-control" value="{{ $info->slots }}" />
        </div>



        <div class="form-group py-1 status {{ $info->status ? '' : 'hidden' }}" >
            <x-input-label for="status">Status</x-input-label>
            <x-text-input name="status" id="status" class="form-control" value="{{ $info->status }}"  />
        </div>

        {{-- @isset($info->location) --}}
        <div class="form-group py-1 location {{ $info->location ? '' : 'hidden' }}" >
            <x-input-label for="location">Localização</x-input-label>
            <x-text-input name="location" id="location" class="form-control" value="{{ $info->location }}" />
        </div>
        {{-- @endisset --}}
    </div>

    <x-primary-button class="mt-2" id="submit">Enviar</x-primary-button>
    
</form>