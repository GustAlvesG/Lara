<div class="row hidden image-preview-row" id="{{ $id_preview }}_preview_row">
    <x-input-label for="image">{{ $name_preview ?? "Imagem" }}</x-input-label>    
    <img id="{{ $id_preview }}_preview" src="#" alt="">
    <span style="cursor: pointer;" class="text-sm underline text-gray-600 dark:text-gray-400 image_preview_remove">
        Remover Imagem
    </span>
</div>

