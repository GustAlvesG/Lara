<form action="{{ $route }}" method="{{ $method ?? 'POST' }}" enctype="multipart/form-data">
    @csrf
    @if(isset($info))
        @method('PUT')
    @endif
    <div class="form-group py-1">
        <x-input-label for="name">Nome</x-input-label>
        <x-text-input name="name" id="name" class="form-control" value=""/>
    </div>
    <div class="form-group py-1">
        <x-input-label for="description">Descrição</x-input-label>
        <textarea name="description" id="description" class="form-control pt-1 description">
            
        </textarea>
    </div>
    <div id="option-area">
        <div class="form-group py-1 image hidden">
            <x-input-label for="image">Imagem</x-input-label>
            {{-- Input Image --}}
            <input type="file" name="image" id="image" class="form-control" />
        </div>
        <div class="form-group py-1 fee hidden" >
            <x-input-label for="fee">Taxa de Matrícula</x-input-label>
            <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="fee" id="fee" class="form-control" value="" />
        </div>
        <div id="pricesRow" class="form-group  prices hidden">
            <div class="row py-1">
                <div class="col-4">
                    <x-input-label class="count_tag name_price create" for="name_price">Título do Preço</x-input-label>
                    <x-text-input name="name_price[]" id="name_price" class="form-control" value="" />
                </div>
                <div class="col-4">
                    <x-input-label class="count_tag price_associated" for="price_associated">Preço Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_associated[]" id="price_associated" class="form-control" value="" />
                </div>
                <div class="col-4">
                    <x-input-label class="count_tag price_not_associated" for="price_not_associated">Preço Não Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_not_associated[]" id="price_not_associated" class="form-control" value="" />
                </div>
            </div>
        </div>

        <div id="dayHourRow" class="form-group py-1 day_hour hidden" >
            <div class="row py-1">
                <div class="col-4">
                    <x-input-label class="count_tag day_hour create" for="day_hour">Dia</x-input-label>
                    <select class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full" name="day[]" id="">
                        <option value="#">Selecione uma opção</option>
                        <option value="Domingo">Domingo</option>
                        <option value="Segunda-feira">Segunda-feira</option>
                        <option value="Terça-feira">Terça-feira</option>
                        <option value="Quarta-feira">Quarta-feira</option>
                        <option value="Quinta-feira">Quinta-feira</option>
                        <option value="Sexta-feira">Sexta-feira</option>
                        <option value="Sábado">Sábado</option>          
                        <option value="Dias de Semana">Dias de Semana</option>
                        <option value="Fim de Semana">Fim de Semana</option> 
                        <option value="Todos os dias">Todos</option>         
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
        
        <div class="form-group py-1 category hidden" >
            <x-input-label for="category">Categoria</x-input-label>
            <x-text-input name="category" id="category" class="form-control"  value="" />
        </div>

        <div id="responsibleRow" class="form-group responsible border-t-4 hidden" >
            <div class="row py-1">
                <div class="col-6">
                    <x-input-label class="count_tag responsible create" for="responsible">Responsável</x-input-label>
                    <x-text-input name="responsible[]" id="responsible" class="form-control"  value="" />
                </div>
                <div class="col-6">
                    <x-input-label class="count_tag responsible_contact" for="responsible_contact">Telefone</x-input-label>
                    <x-text-input name="responsible_contact[]" id="responsible_contact" class="form-control"  value="" />

                </div>
            </div>
        </div>

        <div class="form-group py-1 slots hidden" >
            <x-input-label for="slots">Número de Vagas</x-input-label>
            <x-text-input type="number" min="0" name="slots" id="slots" class="form-control" value="" />
        </div>
        <div class="form-group py-1 status hidden" >
            <x-input-label for="status">Status</x-input-label>
            <x-text-input name="status" id="status" class="form-control" value=""  />
        </div>
        <div class="form-group py-1 location hidden" >
            <x-input-label for="location">Localização</x-input-label>
            <x-text-input name="location" id="location" class="form-control" value="" />
        </div>
    </div>

    <x-primary-button class="mt-2" id="submit">Enviar</x-primary-button>
    
</form>