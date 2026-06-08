<input type="hidden" name="company_id" value="{{ $companyId }}">

<!-- ÁREA DA WEBCAM / FOTO -->
<div class="md:col-span-2 flex flex-col items-center">
    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3 text-center">Foto de Identificação</label>

    <div class="relative w-full max-w-[280px] bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden border-4 border-white dark:border-gray-800 shadow-xl flex items-center justify-center" style="aspect-ratio:1">
        <div id="camera-placeholder" class="flex flex-col items-center text-gray-400 dark:text-gray-500 p-4">
            <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-xs font-bold uppercase tracking-wider text-center">Câmera / Importar</span>
        </div>
        <video id="webcam-video" autoplay playsinline class="hidden w-full h-full object-cover scale-x-[-1]"></video>
        <img id="photo-preview" src="#" alt="Foto capturada" class="hidden w-full h-full object-cover">
        <div id="camera-loading" class="hidden absolute inset-0 bg-white/80 dark:bg-gray-800/80 flex items-center justify-center">
            <div class="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap justify-center gap-2">
        <button type="button" id="btn-start-camera" onclick="startCamera()"
                class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
            Ativar Câmera
        </button>
        <button type="button" onclick="triggerPhotoImport()"
                class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
            Importar Foto
        </button>
        <button type="button" id="btn-take-photo" onclick="takePhoto()"
                class="hidden px-4 py-2 bg-indigo-600 text-white rounded-lg text-xs font-bold uppercase shadow-md hover:bg-indigo-700 transition">
            Tirar Foto
        </button>
        <button type="button" id="btn-reset-photo" onclick="resetCamera()"
                class="hidden px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border border-red-100 dark:border-red-800 rounded-lg text-xs font-bold uppercase hover:bg-red-100 dark:hover:bg-red-900/50 transition">
            Tentar Novamente
        </button>
    </div>

    <input type="hidden" name="image" id="captured-photo-input">
    <input type="file" id="import-photo-input" accept="image/*" class="hidden" onchange="importPhoto(this)">
    <canvas id="photo-canvas" class="hidden" width="600" height="600"></canvas>
</div>


<!-- Nome Completo -->
<div class="md:col-span-2">
    <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome Completo</label>
    <input type="text" id="name" name="name" required placeholder="Ex: João Silva"
            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
</div>

<!-- Email -->
<div>
    <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
    <input type="email" id="email" name="email" placeholder="joao.silva@empresa.com"
            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
</div>

<!-- CPF / Documento -->
<div>
    <label for="cpf-field" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">CPF / Documento</label>
    <input type="text" id="cpf-field" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" autocomplete="off"
            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
    <input type="hidden" id="document-raw" name="document" value="{{ old('document', '') }}">
    <p id="cpf-feedback" class="mt-1 text-xs hidden"></p>
</div>

<!-- Cargo -->
<div>
    <label for="position" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Cargo / Função</label>
    <input type="text" id="position" name="position" required placeholder="Ex: Motorista"
            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
</div>

<!-- Telefone -->
<div>
    <label for="telephone" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Telefone</label>
    <input type="text" id="telephone" name="telephone" placeholder="(24) 99999-9999"
            class="w-full px-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition shadow-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500">
</div>


<script>
    const video       = document.getElementById('webcam-video');
    const canvas      = document.getElementById('photo-canvas');
    const preview     = document.getElementById('photo-preview');
    const placeholder = document.getElementById('camera-placeholder');
    const loading     = document.getElementById('camera-loading');
    const photoInput  = document.getElementById('captured-photo-input');
    const btnStart    = document.getElementById('btn-start-camera');
    const btnTake     = document.getElementById('btn-take-photo');
    const btnReset    = document.getElementById('btn-reset-photo');

    let stream = null;

    // Usamos função nomeada para evitar que `name="document"` no input de CPF
    // sobrescreva o objeto global `document` em handlers onclick inline.
    function triggerPhotoImport() {
        document.getElementById('import-photo-input').click();
    }

    async function startCamera() {
        loading.classList.remove('hidden');
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 600, height: 600, facingMode: 'user' },
                audio: false
            });
            video.srcObject = stream;
            video.classList.remove('hidden');
            placeholder.classList.add('hidden');
            preview.classList.add('hidden');
            btnStart.classList.add('hidden');
            btnTake.classList.remove('hidden');
            btnReset.classList.add('hidden');
        } catch (err) {
            alert('Não foi possível acessar a câmera. Verifique as permissões.');
        } finally {
            loading.classList.add('hidden');
        }
    }

    function takePhoto() {
        const ctx = canvas.getContext('2d');
        ctx.translate(600, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0, 600, 600);
        const dataUrl = canvas.toDataURL('image/jpeg');
        preview.src      = dataUrl;
        photoInput.value = dataUrl;
        video.classList.add('hidden');
        preview.classList.remove('hidden');
        btnTake.classList.add('hidden');
        btnReset.classList.remove('hidden');
        if (stream) stream.getTracks().forEach(t => t.stop());
    }

    function resetCamera() {
        photoInput.value = '';
        preview.src      = '#';
        startCamera();
    }

    function importPhoto(input) {
        if (!input.files || !input.files[0]) return;
        const reader = new FileReader();
        reader.onloadend = function () {
            preview.src      = reader.result;
            photoInput.value = reader.result;
            video.classList.add('hidden');
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            btnStart.classList.remove('hidden');
            btnTake.classList.add('hidden');
            btnReset.classList.remove('hidden');
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        };
        reader.readAsDataURL(input.files[0]);
    }

    function clearCameraState() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        photoInput.value = '';
        preview.src      = '#';
        preview.classList.add('hidden');
        video.classList.add('hidden');
        placeholder.classList.remove('hidden');
        btnStart.classList.remove('hidden');
        btnTake.classList.add('hidden');
        btnReset.classList.add('hidden');
        const importInput = document.getElementById('import-photo-input');
        if (importInput) importInput.value = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');
        if (form) form.addEventListener('reset', clearCameraState);

        if (form) form.addEventListener('submit', function (e) {
            const digits = document.getElementById('document-raw').value;
            if (digits.length > 0 && digits.length !== 11) {
                e.preventDefault();
                const fb = document.getElementById('cpf-feedback');
                fb.textContent = '✗ CPF incompleto — informe todos os 11 dígitos';
                fb.className   = 'mt-1 text-xs text-red-600 dark:text-red-400';
                document.getElementById('cpf-field').focus();
            }
        });

        const cpfField    = document.getElementById('cpf-field');
        const cpfRaw      = document.getElementById('document-raw');
        const cpfFeedback = document.getElementById('cpf-feedback');

        function formatCPF(digits) {
            return digits
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }

        function validateCPF(cpf) {
            if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
            for (let t = 9; t < 11; t++) {
                let d = 0;
                for (let c = 0; c < t; c++) d += parseInt(cpf[c]) * ((t + 1) - c);
                d = ((10 * d) % 11) % 10;
                if (parseInt(cpf[t]) !== d) return false;
            }
            return true;
        }

        function showFeedback(digits) {
            if (digits.length === 0) { cpfFeedback.classList.add('hidden'); return; }
            cpfFeedback.classList.remove('hidden');
            if (digits.length < 11) {
                cpfFeedback.textContent = '✗ CPF incompleto (' + digits.length + '/11 dígitos)';
                cpfFeedback.className   = 'mt-1 text-xs text-red-600 dark:text-red-400';
                return;
            }
            const valid = validateCPF(digits);
            cpfFeedback.textContent = valid ? '✓ CPF válido' : '✗ CPF inválido';
            cpfFeedback.className   = valid
                ? 'mt-1 text-xs text-green-600 dark:text-green-400'
                : 'mt-1 text-xs text-red-600 dark:text-red-400';
        }

        cpfField.addEventListener('input', function () {
            const digits    = this.value.replace(/\D/g, '').slice(0, 11);
            this.value      = formatCPF(digits);
            cpfRaw.value    = digits;
            showFeedback(digits);
        });

        // Initialize from old() value if present
        const initial = cpfRaw.value.replace(/\D/g, '').slice(0, 11);
        if (initial) {
            cpfField.value = formatCPF(initial);
            cpfRaw.value   = initial;
            showFeedback(initial);
        }
    });
</script>
