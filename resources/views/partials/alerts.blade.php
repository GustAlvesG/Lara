<!-- MENSAGENS DE FEEDBACK (NOVO) -->
@if(session('success') || isset($_GET['success']))
<div id="success-alert" class="mb-6 animate-fadeIn">
    <div class="bg-green-600 border border-green-500 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-white/20 p-2 rounded-full">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div>
                <p class="font-extrabold text-lg leading-none">Sucesso!</p>
                <p class="text-sm text-green-100 mt-1">{{ session('success') ?? ($_GET['success'] ?? '') }}</p>
            </div>
        </div>
        <button onclick="document.getElementById('success-alert').remove()" class="text-white/60 hover:text-white transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
@endif
@if(session('error') || isset($_GET['error']))
<div id="error-alert" class="mb-6 animate-fadeIn">
    <div class="bg-red-600 border border-red-500 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="bg-white/20 p-2 rounded-full">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div>
                <p class="font-extrabold text-lg leading-none">Erro!</p>
                <p class="text-sm text-red-100 mt-1">Por favor, tente novamente ou entre em contato com a TI.</p>
                <p class="text-sm text-red-100 mt-1">{{ session('error') ?? '' }}</p>
            </div>
        </div>
        <button onclick="document.getElementById('error-alert').remove()" class="text-white/60 hover:text-white transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>
</div>
@endif