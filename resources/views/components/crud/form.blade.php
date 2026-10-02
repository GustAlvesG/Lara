@props(['formRoute', 'formMethod' => 'POST', 'hasImageSection' => false, 'existingImageUrl' => null])
<div class="max-w-3xl mx-auto">

{{ $header ?? '' }}

<div class="bg-surface my-4 rounded-2xl shadow-pop border border-line overflow-hidden">
    <form action="{{ $formRoute }}" method="{{ $formMethod }}" enctype="multipart/form-data" class="p-8">
        @csrf
        <div class="grid grid-cols-1 gap-6">

            @if($hasImageSection)
            <!-- Secção de Imagem / Logo -->
            <div class="md:col-span-2 flex flex-col items-center justify-center p-6 border-2 border-dashed border-line rounded-2xl bg-subtle hover:bg-subtle transition relative group">
                <div id="preview-container" class="{{ $existingImageUrl ? 'flex' : 'hidden' }} flex flex-col items-center">
                    <img id="image-preview" src="{{ $existingImageUrl ?? '#' }}" alt="Pré-visualização" class="h-32 w-32 object-cover rounded-xl shadow-card mb-3 border-4 border-white">
                    <button type="button" onclick="resetImage()" class="text-xs font-bold text-danger hover:underline">Remover Imagem</button>
                </div>

                <div id="upload-placeholder" class="{{ $existingImageUrl ? 'hidden' : 'flex' }} flex flex-col items-center">
                    <div class="p-4 bg-surface rounded-full shadow-card mb-3 text-grena-ink group-hover:scale-110 transition duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-ink">Imagem</span>
                    <span class="text-xs text-ink-3">Clique para selecionar ou arraste um ficheiro</span>
                </div>

                <input type="file" name="image" id="image-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile()">
            </div>
            @endif

            {{ $fields }}

        </div>

        <!-- Footer do Card -->
        <div class="mt-8 pt-6 border-t border-line flex justify-end gap-3">
            <button type="reset"
                    class="px-6 py-3 bg-surface text-ink rounded-xl font-bold shadow-card hover:bg-subtle border border-line transition">
                Limpar Dados
            </button>
            <button type="submit"
                    class="px-8 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition transform hover:scale-[1.02] focus:ring-2 focus:ring-offset-2 focus:ring-grena-tint">
                Finalizar Registo
            </button>
        </div>
    </form>
</div>
</div>

<script>
    function previewFile() {
        const preview     = document.getElementById('image-preview');
        const file        = document.getElementById('image-input').files[0];
        const container   = document.getElementById('preview-container');
        const placeholder = document.getElementById('upload-placeholder');
        const reader      = new FileReader();

        reader.onloadend = function () {
            preview.src = reader.result;
            container.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };

        if (file) {
            reader.readAsDataURL(file);
        } else {
            resetImage();
        }
    }

    function resetImage() {
        const preview     = document.getElementById('image-preview');
        const fileInput   = document.getElementById('image-input');
        const container   = document.getElementById('preview-container');
        const placeholder = document.getElementById('upload-placeholder');

        if (fileInput)   fileInput.value = '';
        if (preview)     preview.src = '#';
        if (container)   container.classList.add('hidden');
        if (placeholder) placeholder.classList.remove('hidden');
    }
</script>
