@props(['formRoute', 'formMethod' => 'POST', 'hasImageSection' => false, 'existingImageUrl' => null])
<div class="max-w-3xl mx-auto">

{{ $header ?? '' }}

<div class="bg-white dark:bg-gray-800 my-4 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
    <form action="{{ $formRoute }}" method="{{ $formMethod }}" enctype="multipart/form-data" class="p-8">
        @csrf
        <div class="grid grid-cols-1 gap-6">

            @if($hasImageSection)
            <!-- Secção de Imagem / Logo -->
            <div class="md:col-span-2 flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-200 dark:border-gray-600 rounded-2xl bg-gray-50 dark:bg-gray-700/40 hover:bg-gray-100 dark:hover:bg-gray-700/60 transition relative group">
                <div id="preview-container" class="{{ $existingImageUrl ? 'flex' : 'hidden' }} flex flex-col items-center">
                    <img id="image-preview" src="{{ $existingImageUrl ?? '#' }}" alt="Pré-visualização" class="h-32 w-32 object-cover rounded-xl shadow-md mb-3 border-4 border-white dark:border-gray-600">
                    <button type="button" onclick="resetImage()" class="text-xs font-bold text-red-600 dark:text-red-400 hover:underline">Remover Imagem</button>
                </div>

                <div id="upload-placeholder" class="{{ $existingImageUrl ? 'hidden' : 'flex' }} flex flex-col items-center">
                    <div class="p-4 bg-white dark:bg-gray-700 rounded-full shadow-sm mb-3 text-indigo-500 group-hover:scale-110 transition duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Imagem</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">Clique para selecionar ou arraste um ficheiro</span>
                </div>

                <input type="file" name="image" id="image-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile()">
            </div>
            @endif

            {{ $fields }}

        </div>

        <!-- Footer do Card -->
        <div class="mt-8 pt-6 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-3">
            <button type="reset"
                    class="px-6 py-3 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-bold shadow-md hover:bg-gray-50 dark:hover:bg-gray-600 border border-gray-200 dark:border-gray-600 transition">
                Limpar Dados
            </button>
            <button type="submit"
                    class="px-8 py-3 bg-indigo-600 text-white rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition transform hover:scale-[1.02] focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
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
