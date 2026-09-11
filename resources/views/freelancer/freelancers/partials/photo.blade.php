{{--
    Foto de identificação do freelancer — a mesma captura do cadastro de
    terceirizado (câmera ou importação), para a portaria reconhecer quem entra.

    O campo `image` só vai preenchido quando há foto NOVA: vazio, o servidor
    mantém a que já está gravada.
--}}
@php
    $currentPhoto = $freelancer?->imageUrl();
@endphp

<div class="md:col-span-2 flex flex-col items-center">
    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-3 text-center">Foto de Identificação</label>

    <div class="relative w-full max-w-[220px] bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden border-4 border-white dark:border-gray-800 shadow-xl flex items-center justify-center" style="aspect-ratio:1">
        <div id="fl-photo-placeholder" class="{{ $currentPhoto ? 'hidden' : '' }} flex flex-col items-center text-gray-400 dark:text-gray-500 p-4">
            <svg class="w-16 h-16 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            <span class="text-xs font-bold uppercase tracking-wider text-center">Câmera / Importar</span>
        </div>
        <video id="fl-photo-video" autoplay playsinline class="hidden w-full h-full object-cover scale-x-[-1]"></video>
        <img id="fl-photo-preview" src="{{ $currentPhoto ?? '#' }}" alt="Foto do freelancer"
             class="{{ $currentPhoto ? '' : 'hidden' }} w-full h-full object-cover">
        <div id="fl-photo-loading" class="hidden absolute inset-0 bg-white/80 dark:bg-gray-800/80 flex items-center justify-center">
            <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap justify-center gap-2">
        <button type="button" id="fl-photo-start"
                class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
            Ativar Câmera
        </button>
        <button type="button" id="fl-photo-import"
                class="px-4 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg text-xs font-bold uppercase shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 transition">
            Importar Foto
        </button>
        <button type="button" id="fl-photo-take"
                class="hidden px-4 py-2 bg-[#A00001] text-white rounded-lg text-xs font-bold uppercase shadow-md hover:bg-[#800000] transition">
            Tirar Foto
        </button>
        <button type="button" id="fl-photo-undo"
                class="hidden px-4 py-2 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 border border-red-100 dark:border-red-800 rounded-lg text-xs font-bold uppercase hover:bg-red-100 dark:hover:bg-red-900/50 transition">
            {{ $currentPhoto ? 'Manter a Foto Atual' : 'Descartar' }}
        </button>
    </div>

    @error('image')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror

    <input type="hidden" name="image" id="fl-photo-input">
    <input type="file" id="fl-photo-file" accept="image/jpeg,image/png,image/webp" class="hidden">
    <canvas id="fl-photo-canvas" class="hidden" width="600" height="600"></canvas>
</div>

<script>
(function () {
    const SIZE = 600;
    const current = @json($currentPhoto);

    const video       = document.getElementById('fl-photo-video');
    const canvas      = document.getElementById('fl-photo-canvas');
    const preview     = document.getElementById('fl-photo-preview');
    const placeholder = document.getElementById('fl-photo-placeholder');
    const loading     = document.getElementById('fl-photo-loading');
    const input       = document.getElementById('fl-photo-input');
    const file        = document.getElementById('fl-photo-file');
    const btnStart    = document.getElementById('fl-photo-start');
    const btnImport   = document.getElementById('fl-photo-import');
    const btnTake     = document.getElementById('fl-photo-take');
    const btnUndo     = document.getElementById('fl-photo-undo');

    let stream = null;

    function stopCamera() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        video.classList.add('hidden');
        btnTake.classList.add('hidden');
        btnStart.classList.remove('hidden');
    }

    function show(src) {
        preview.src = src;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
    }

    // Toda foto nova sai do canvas: quadrada, 600px, JPEG. A importada é
    // recortada no centro — uma foto de celular crua passaria de vários MB.
    function useCanvas() {
        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
        input.value = dataUrl;
        show(dataUrl);
        btnUndo.classList.remove('hidden');
    }

    btnStart.addEventListener('click', async function () {
        loading.classList.remove('hidden');
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: SIZE, height: SIZE, facingMode: 'user' },
                audio: false
            });
            video.srcObject = stream;
            video.classList.remove('hidden');
            preview.classList.add('hidden');
            placeholder.classList.add('hidden');
            btnStart.classList.add('hidden');
            btnTake.classList.remove('hidden');
            btnUndo.classList.remove('hidden');
        } catch (err) {
            alert('Não foi possível acessar a câmera. Verifique as permissões.');
        } finally {
            loading.classList.add('hidden');
        }
    });

    btnTake.addEventListener('click', function () {
        const ctx = canvas.getContext('2d');
        const side = Math.min(video.videoWidth, video.videoHeight);
        ctx.save();
        ctx.translate(SIZE, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, (video.videoWidth - side) / 2, (video.videoHeight - side) / 2, side, side, 0, 0, SIZE, SIZE);
        ctx.restore();
        stopCamera();
        useCanvas();
    });

    btnImport.addEventListener('click', () => file.click());

    file.addEventListener('change', function () {
        if (!file.files || !file.files[0]) return;
        const img = new Image();
        img.onload = function () {
            const side = Math.min(img.width, img.height);
            canvas.getContext('2d').drawImage(img, (img.width - side) / 2, (img.height - side) / 2, side, side, 0, 0, SIZE, SIZE);
            URL.revokeObjectURL(img.src);
            stopCamera();
            useCanvas();
        };
        img.onerror = () => alert('Não foi possível abrir esta imagem.');
        img.src = URL.createObjectURL(file.files[0]);
        file.value = '';
    });

    // Desfaz a foto nova: volta para a gravada (ou para o vazio, no cadastro).
    btnUndo.addEventListener('click', function () {
        stopCamera();
        input.value = '';
        btnUndo.classList.add('hidden');
        if (current) {
            show(current);
        } else {
            preview.src = '#';
            preview.classList.add('hidden');
            placeholder.classList.remove('hidden');
        }
    });
})();
</script>
