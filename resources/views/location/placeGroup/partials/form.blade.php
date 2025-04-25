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
                    <option value="esportiva" {{ $item->category == 'esportiva' ? 'selected' : '' }} >Esportiva</option>
                    <option value="social" {{ $item->category == 'social' ? 'selected' : '' }} disabled>Social</option>
                </select>
            </div>
        </div>
        <div class="row">
            <x-input-label for="name">Imagens</x-input-label>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Insira as imagens para exibição no site.") }}
            </p>
            <div class="col">
                <div class="form-group py-1 image">
                    <x-input-label for="image">Imagem Vertical</x-input-label>
                    {{-- Input Image --}}
                    <input type="file" name="image_vertical"  class="form-control" value={{ $item->image_vertical ?? null }} />
                    {{-- Display Image --}}

                </div>
            </div>
            <div class="col">
                <div class="form-group py-1 image">
                    <x-input-label for="image">Imagem Horizontal</x-input-label>
                    {{-- Input Image --}}
                    <input type="file" name="image_horizontal"  class="form-control" value={{ $item->image_horizontal ?? null }} />
                </div>
            </div>
        </div>
    </div>
</div>