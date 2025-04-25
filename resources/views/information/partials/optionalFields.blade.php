{{-- // Adicionado checkbox --}}
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="image" id="image" value="1">
    <label class="form-check text-gray-900 dark:text-gray-100" for="image">
        Imagem
    </label>
</div>
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="prices" id="prices" value="1"
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->priceAssociated == 0)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="prices">
        Preço Sócio / Não Sócio
    </label>
</div>
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="category" id="category" value="1"
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->category == null)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="category">
        Categoria
    </label>
</div>
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="responsible" id="responsible" value="1"
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->responsible == null)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="responsible">
        Responsável
    </label>
</div>
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="slots" id="slots" value="1"
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->slots == null)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="slots">
        Número de Vagas
    </label>
</div>
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="status" id="status" value="1"
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->status == null)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="status">
        Status
    </label>
</div>
  
<div class="form-check py-1">
    <input class="form-check optional-field" type="checkbox" name="location" id="location" value="1" 
    @unless(Route::currentRouteNamed('information.create') || (Route::currentRouteNamed('information.edit') && $info->location == null)) checked @endunless>
    <label class="form-check text-gray-900 dark:text-gray-100" for="location">
        Localização
    </label>
</div>