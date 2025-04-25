<div id="outers">

    <div class="form-group py-1">
        <x-input-label for="name">Nome</x-input-label>
        <x-text-input name="name" id="name" class="form-control" value=""/>
    </div>


    <div class="form-group py-1">
        <x-input-label for="cpf">CPF</x-input-label>
        <x-text-input name="cpf" id="cpf" class="form-control" value=""/>
    </div>

    <div class="form-group py-1">
        <x-input-label for="telephone">Telefone</x-input-label>
        <x-text-input name="telephone" id="telephone" class="form-control" value=""/>
    </div>

    <div class="form-group py-1">
        <x-input-label for="image">Imagem</x-input-label>
        <div class="row">
            <div class="col">
                <x-secondary-button onClick="take_snapshot()" class="mt-2" type="button" id="submit">Capturar Imagem</x-secondary-button>
            </div>
            <div class="col">
                <label for="upload-photo" style="cursor: pointer;" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150 mt-2">Selecionar...</label>
                <input type="file" name="image" id="upload-photo" style="opacity: 0; position: absolute; z-index: -1;" />
            </div>
        </div>
        <input type="hidden" name="photo" class="image-tag">
        <div id="results" class="py-2"></div>

    </div>

</div>

<hr>

