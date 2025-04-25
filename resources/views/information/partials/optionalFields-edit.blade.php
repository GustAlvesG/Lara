{{-- // Adicionado checkbox --}}
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="image" id="image" value="1">
    <label class="form-check text-gray-900 dark:text-gray-100" for="image">
        Imagem
    </label>
</div>

<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="fee" id="fee" value="1"
    @unless(Route::currentRouteNamed('information.edit') && $info->fee == 0) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="fee">
        Taxa de Matrícula
    </label>
</div>

@php
    $isCheckedPrice = count($info->name_price) === 0 && count($info->price_associated) === 0 && count($info->price_not_associated) === 0 ? true : false;
    $isCheckedDayHour = count($info->day_hour) === 0 ? false : true;
    $isCheckedResponsible = count($info->responsible) === 0 ? false : true;
@endphp


<div class="form-check py-1">
    <input {{ $isCheckedPrice ? '' : 'checked' }} class="form-check optional-field" type="checkbox" name="prices" id="prices" value="1"
    >
    <label class="form-check text-gray-900 dark:text-gray-100" for="prices">
        Preço Sócio / Não Sócio
    </label>
</div>

<div class="form-check py-1">
    <input {{ $isCheckedDayHour ? 'checked' : '' }} class="form-check optional-field" type="checkbox" name="day_hour" id="day_hour" value="1">
    <label class="form-check text-gray-900 dark:text-gray-100" for="day_hour">
        Dia e Horário
    </label>
</div>

<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="category" id="category" value="1"
    @unless(Route::currentRouteNamed('information.edit') && $info->category == null) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="category">
        Categoria
    </label>
</div>

<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="responsible" id="responsible" value="1"
    {{ $isCheckedResponsible ? 'checked' : '' }}>
    <label class="form-check text-gray-900 dark:text-gray-100" for="responsible">
        Responsável
    </label>
</div>

<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="slots" id="slots" value="1"
    @unless(Route::currentRouteNamed('information.edit') && $info->slots == null) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="slots">
        Número de Vagas
    </label>
</div>

<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="status" id="status" value="1"
    @unless(Route::currentRouteNamed('information.edit') && $info->status == null) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="status">
        Status
    </label>
</div>
  
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="location" id="location" value="1" 
    @unless(Route::currentRouteNamed('information.edit') && $info->location == null) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="location">
        Localização
    </label>
</div>