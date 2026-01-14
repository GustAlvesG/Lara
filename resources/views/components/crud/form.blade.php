<div class="max-w-3xl mx-auto">

{{ $header ?? '' }}

<div class="bg-white my-4 rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
    <form action="{{ $formRoute }}" method="{{ $formMethod }}" enctype="multipart/form-data" class="p-8">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            @if($hasImageSection)
            <!-- Secção de Imagem / Logo -->
            <div class="md:col-span-2 flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-200 rounded-2xl bg-gray-50 hover:bg-gray-100 transition relative group">
                <div id="preview-container" class="hidden flex flex-col items-center">
                    <img id="image-preview" src="#" alt="Pré-visualização" class="h-32 w-32 object-cover rounded-xl shadow-md mb-3 border-4 border-white">
                    <button type="button" onclick="resetImage()" class="text-xs font-bold text-red-600 hover:underline">Remover Imagem</button>
                </div>
                
                <div id="upload-placeholder" class="flex flex-col items-center">
                    <div class="p-4 bg-white rounded-full shadow-sm mb-3 text-indigo-500 group-hover:scale-110 transition duration-300">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-bold text-gray-700">Imagem</span>
                    <span class="text-xs text-gray-400">Clique para selecionar ou arraste um ficheiro</span>
                </div>

                <input type="file" name="image" id="image-input" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewFile()">
            </div>

            @endif

            <div id="messages">{{ $message ?? '' }}</div>

            {{ $fields }}

        </div>
        <!-- Footer do Card -->
        <div class="mt-8 pt-6 border-t border-gray-100 flex justify-end gap-3">
            <button type="reset" onclick="resetImage()" class="px-6 py-3 bg-white text-gray-700 rounded-xl font-bold shadow-md hover:bg-gray-50 border border-gray-200 transition">
                Limpar Dados
            </button>
            <button type="submit" class="px-8 py-3 bg-indigo-600 text-white rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition transform hover:scale-[1.02] focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Finalizar Registo
            </button>
        </div>
    </form>
    </div>
</div>

 <script>
    /**
     * Lógica de Pré-visualização de Imagem
     */
    function previewFile() {
        const preview = document.getElementById('image-preview');
        const file = document.getElementById('image-input').files[0];
        const container = document.getElementById('preview-container');
        const placeholder = document.getElementById('upload-placeholder');
        const reader = new FileReader();

        reader.onloadend = function () {
            preview.src = reader.result;
            container.classList.remove('hidden');
            placeholder.classList.add('hidden');
        }

        if (file) {
            reader.readAsDataURL(file);
        } else {
            resetImage();
        }
    }

    function resetImage() {
        const preview = document.getElementById('image-preview');
        const fileInput = document.getElementById('image-input');
        const container = document.getElementById('preview-container');
        const placeholder = document.getElementById('upload-placeholder');

        fileInput.value = "";
        preview.src = "#";
        container.classList.add('hidden');
        placeholder.classList.remove('hidden');
    }

    function showMessage(type, message) {
        // Implementação básica de mensagem compatível com dark mode
        const msgDiv = document.getElementById('messages');
        const colorClass = type === 'error' ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-100' : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-100';
        msgDiv.innerHTML = `<div class="p-4 mb-4 rounded-lg ${colorClass}">${message}</div>`;
        setTimeout(() => msgDiv.innerHTML = '', 5000);
    }
</script>