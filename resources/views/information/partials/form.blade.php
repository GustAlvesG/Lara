<form action="{{ $route }}" method="{{ $method ?? 'POST' }}" enctype="multipart/form-data">
    @csrf
    @if(isset($info))
        @method('PUT')
    @endif
    <div class="form-group py-1">
        <x-input-label for="name">Nome</x-input-label>
        <x-text-input name="name" id="name" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->name : '' }}"/>
    </div>
    <div class="form-group py-1">
        <x-input-label for="description">Descrição</x-input-label>
        <textarea name="description" id="description" class="form-control pt-1">
            {{ Route::currentRouteNamed('information.edit') ? $info->name : '' }}
        </textarea>
    </div>
    <div id="option-area">
        <div class="form-group py-1 image hidden">
            <x-input-label for="image">Imagem</x-input-label>
            {{-- Input Image --}}
            <input type="file" name="image" id="image" class="form-control" />
        </div>
        <div class="form-group py-1 prices {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->priceAssociated == null ? 'hidden' : '' }}" >
            <div class="row">
                <div class="col-6">
                    <x-input-label for="priceAssociated">Preço Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="priceAssociated" id="priceAssociated" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->priceAssociated : '' }}" />
                </div>
                <div class="col-6">
                    <x-input-label for="priceNotAssociated">Preço Não Sócio</x-input-label>
                    <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="priceNotAssociated" id="priceNotAssociated" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->priceNotAssociated : '' }}" />
                </div>
            </div>
        </div>
        <div class="form-group py-1 category {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->category == null ? 'hidden' : '' }}" >
            <x-input-label for="category">Categoria</x-input-label>
            <x-text-input name="category" id="category" class="form-control"  value="{{ Route::currentRouteNamed('information.edit') ? $info->category : '' }}" />
        </div>

        <div class="form-group py-1 responsible {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->responsible == null ? 'hidden' : '' }}" >
            <x-input-label for="responsible">Responsável</x-input-label>
            <x-text-input name="responsible" id="responsible" class="form-control"  value="{{ Route::currentRouteNamed('information.edit') ? $info->responsible : '' }}" />
        </div>

        <div class="form-group py-1 slots {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->slots == null ? 'hidden' : '' }}" >
            <x-input-label for="slots">Número de Vagas</x-input-label>
            <x-text-input type="number" min="0" name="slots" id="slots" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->slots : '' }}" />
        </div>
        <div class="form-group py-1 status {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->status == null ? 'hidden' : '' }}" >
            <x-input-label for="status">Status</x-input-label>
            <x-text-input name="status" id="status" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->status : '' }}"  />
        </div>
        <div class="form-group py-1 location {{ Route::currentRouteNamed('information.create') ? 'hidden' : '' }} {{ Route::currentRouteNamed('information.edit') && $info->location == null ? 'hidden' : '' }}" >
            <x-input-label for="location">Localização</x-input-label>
            <x-text-input name="location" id="location" class="form-control" value="{{ Route::currentRouteNamed('information.edit') ? $info->location : '' }}" />
        </div>
    </div>

    <x-secondary-button class="mt-2" id="add-option">Cancelar</x-secondary-button>
    <x-primary-button class="mt-2" id="submit">Enviar</x-primary-button>
    
</form>