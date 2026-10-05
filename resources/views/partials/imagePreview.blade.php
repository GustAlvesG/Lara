<div class="row hidden image-preview-row" id="{{ $id_preview }}_preview_row">
    <x-input-label for="image">{{ $name_preview ?? "Imagem" }}</x-input-label>    
    <img id="{{ $id_preview }}_preview" src="#" alt="">
    <span style="cursor: pointer;" class="text-sm underline text-ink-2 image_preview_remove">
        Remover Imagem
    </span>
</div>

