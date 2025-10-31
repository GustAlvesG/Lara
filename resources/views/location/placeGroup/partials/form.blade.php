<div class="row">

    <div class="col-6">
        <div class="form-group py-1">
            <x-input-label for="name">Nome do Grupo</x-input-label>
            
            <x-text-input name="name" id="name" class="form-control w-full" value="{{ $item->name ?? '' }}" required/>
        </div>
        <div class="row">
            <div class="form-check py-4">
                <label class="form-check text-gray-900 dark:text-gray-100" for="category">
                    Tipo de Regra
                </label>
                <select name="category" class=" border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm w-full">
                    <option value="esportiva" {{ (isset($item) && $item->category == 'esportiva') ? 'selected' : '' }}>Esportiva</option>
                    <option value="social" {{ (isset($item) && $item->category == 'social') ? 'selected' : '' }} disabled>Social</option>
                </select>
            </div>
        </div>
        <div class="row">
            <x-input-label for="name">Imagens</x-input-label>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Insira as imagens para exibição no site.") }}
            </p>
             <div class="col">
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __("Imagem Vertical") }}
                </p>
                <div class="form-group py-1 image">
                    {{-- Input Image --}}
                    <label for="image_vertical" style="cursor: pointer;" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150 mt-2">Selecionar...</label>
                    <input type="file" class="form-control image-upload" name="image_vertical" id="image_vertical" style="opacity: 0; position: absolute; z-index: -1;" />
                </div>
            </div>
             <div class="col">
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __("Imagem Horizontal") }}
                </p>
                <div class="form-group image">
                    {{-- Input Image --}}
                    <label for="image_horizontal" style="cursor: pointer;" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150 mt-2">Selecionar...</label>
                    <input type="file" class="form-control image-upload" name="image_horizontal" id="image_horizontal" style="opacity: 0; position: absolute; z-index: -1;" />
                </div>
            </div>
           
        </div>
    </div>
    <div class="col-6">
        {{-- This row will be to show preview images --}}
        @include('partials.imagePreview', ['id_preview' => 'image_vertical']) 
        @include('partials.imagePreview', ['id_preview' => 'image_horizontal'])    
    </div>
</div>