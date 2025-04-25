<div class="row">
    <div class="col-6">
        <div class="form-group py-1">
            <x-input-label for="name">Nome</x-input-label>
            <x-text-input name="name" id="name" class="form-control" value="{{ $item->name ?? '' }}" required/>
        </div>
        <div class="row">
            <div class="col">
                <div class="form-group py-1">
                    <x-input-label for="email">E-mail</x-input-label>
                    <x-text-input type="email" name="email" id="email" class="form-control" value="{{ $item->email ?? '' }}" required required/>
                </div>
            </div>
            <div class="col">
                <div class="form-group py-1">
                    <x-input-label for="telephone">Telefone</x-input-label>
                    <x-text-input name="telephone" id="telephone" class="form-control" value="{{ $item->telephone ?? '' }}" required/>
                </div>
            </div>
        </div>
    
    
        <div class="form-group py-1">
            <x-input-label for="address">Endereço</x-input-label>
            <x-text-input name="address" id="address" class="form-control" value="{{ $item->address ?? '' }}" required/>
        </div>
        <div class="form-group py-1">
            <x-input-label for="description">Descrição</x-input-label>
            <textarea name="description" id="description" class="form-control pt-1 description">
                    
            </textarea>
        </div>
        <div class="form-group py-1 image">
            <x-input-label for="image">Imagem</x-input-label>
            {{-- Input Image --}}
            <input type="file" name="image" id="image" class="form-control" />
        </div>
    </div>
    <div class="col-6 ">
        <div class="row text-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Regras de Acesso
            </h2>
        </div>
        <div class="row rules">
            @include('company.partials.rules-form')
        </div>
    </div>

    
</div>