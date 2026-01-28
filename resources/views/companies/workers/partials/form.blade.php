<input type="hidden" name="company_id" value="{{ $companyId }}">

<!-- ÁREA DA WEBCAM / FOTO -->
<div class="md:col-span-2 flex flex-col items-center">
    <label class="block text-sm font-bold text-gray-700 mb-3 text-center">Foto de Identificação</label>
    
    <div class="relative w-full max-w-[280px] camera-box bg-gray-100 rounded-full overflow-hidden border-4 border-white shadow-xl flex items-center justify-center group">
        
        <!-- Placeholder Inicial -->
        <div id="camera-placeholder" class="flex flex-col items-center text-gray-400">
            <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-xs font-bold uppercase tracking-wider">Câmera Desligada</span>
        </div>

        <!-- Vídeo da Webcam (Oculto inicialmente) -->
        <video id="webcam-video" autoplay playsinline class="hidden w-full h-full object-cover scale-x-[-1]"></video>
        
        <!-- Preview da Foto Capturada (Oculto inicialmente) -->
        <img id="photo-preview" src="#" alt="Foto capturada" class="hidden w-full h-full object-cover">

        <!-- Overlay de Loading -->
        <div id="camera-loading" class="hidden absolute inset-0 bg-white/80 flex items-center justify-center">
            <div class="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>

    <!-- Controles da Câmera -->
    <div class="mt-4 flex gap-3">
        <button type="button" id="btn-start-camera" onclick="startCamera()" class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 transition">
            Ativar Câmera
        </button>
        <button type="button" id="btn-take-photo" onclick="takePhoto()" class="hidden px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold uppercase shadow-md hover:bg-indigo-700 transition">
            Tirar Foto
        </button>
        <button type="button" id="btn-reset-photo" onclick="resetCamera()" class="hidden px-4 py-2 bg-red-50 text-red-600 border border-red-100 rounded-lg text-xs font-bold uppercase hover:bg-red-100 transition">
            Tentar Novamente
        </button>
    </div>

    <!-- Input oculto para enviar a imagem Base64 -->
    <input type="hidden" name="image" id="captured-photo-input">
    <!-- Canvas oculto para processamento -->
    <canvas id="photo-canvas" class="hidden" width="600" height="600"></canvas>
</div>


<!-- Nome Completo -->
<div class="md:col-span-2">
    <label for="name" class="block text-sm font-bold text-gray-700 mb-1">Nome Completo</label>
    <input type="text" id="name" name="name" required placeholder="Ex: João Silva"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- Email -->
<div>
    <label for="email" class="block text-sm font-bold text-gray-700 mb-1">E-mail</label>
    <input type="email" id="email" name="email" required placeholder="joao.silva@empresa.com"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- CPF / Documento -->
<div>
    <label for="document" class="block text-sm font-bold text-gray-700 mb-1">CPF / Documento</label>
    <input type="text" id="document" name="document" placeholder="000.000.000-00"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- Cargo -->
<div>
    <label for="position" class="block text-sm font-bold text-gray-700 mb-1">Cargo / Função</label>
    <input type="text" id="position" name="position" required placeholder="Ex: Motorista"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>

<!-- Telefone -->
<div>
    <label for="telephone" class="block text-sm font-bold text-gray-700 mb-1">Telefone</label>
    <input type="text" id="telephone" name="telephone" placeholder="(24) 99999-9999"
            class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm">
</div>


<script>
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('photo-canvas');
    const preview = document.getElementById('photo-preview');
    const placeholder = document.getElementById('camera-placeholder');
    const loading = document.getElementById('camera-loading');
    const photoInput = document.getElementById('captured-photo-input');

    const btnStart = document.getElementById('btn-start-camera');
    const btnTake = document.getElementById('btn-take-photo');
    const btnReset = document.getElementById('btn-reset-photo');

    let stream = null;

    /**
        * Inicia a captura da Webcam
        */
    async function startCamera() {
        loading.classList.remove('hidden');
        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                video: { width: 600, height: 600, facingMode: "user" }, 
                audio: false 
            });
            video.srcObject = stream;
            
            // UI Update
            video.classList.remove('hidden');
            placeholder.classList.add('hidden');
            preview.classList.add('hidden');
            
            btnStart.classList.add('hidden');
            btnTake.classList.remove('hidden');
            btnReset.classList.add('hidden');

        } catch (err) {
            console.error("Erro ao acessar webcam:", err);
            alert("Não foi possível acessar a câmera. Verifique as permissões.");
        } finally {
            loading.classList.add('hidden');
        }
    }

    /**
        * Tira a foto e congela o frame
        */
    function takePhoto() {
        const context = canvas.getContext('2d');
        
        // Inverter horizontalmente no canvas para bater com o espelho do vídeo
        context.translate(600, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, 600, 600);
        
        const dataUrl = canvas.toDataURL('image/jpeg');
        
        // Atualiza campos
        preview.src = dataUrl;
        photoInput.value = dataUrl;
        
        // UI Update
        video.classList.add('hidden');
        preview.classList.remove('hidden');
        btnTake.classList.add('hidden');
        btnReset.classList.remove('hidden');

        // Para o stream para economizar recursos
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
    }

    /**
        * Reseta para tirar outra foto
        */
    function resetCamera() {
        photoInput.value = "";
        preview.src = "#";
        startCamera();
    }
</script>