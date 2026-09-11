<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

@php
    $input = 'w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-red-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white';
    $semAssinatura = !$current?->hasSignature();
@endphp

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Diretoria</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Quem recebe o e-mail com os códigos do lote e assina, pelo CONTRATANTE, os contratos da redação 2.
            </p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        @if(!$current)
            <div class="mb-6 p-4 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 rounded-lg text-sm font-medium">
                ⚠️ Nenhum diretor cadastrado. Enquanto não houver, nenhum lote pode ser enviado à diretoria.
            </div>
        @elseif($semAssinatura)
            <div class="mb-6 p-4 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 rounded-lg text-sm font-medium">
                ⚠️ Falta a <b>imagem da assinatura</b>. Sem ela, lotes com contratos da redação 2 não seguem para a
                diretoria — a assinatura do diretor é o que vai no documento quando ele aprova.
            </div>
        @endif

        {{-- ============ CADASTRO VIGENTE ============ --}}
        @if($current)
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Cadastro vigente</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Desde {{ $current->created_at?->format('d/m/Y H:i') }}
                        @if($current->createdBy) · por {{ $current->createdBy->name }} @elseif(!$current->created_by) · importado do .env @endif
                    </p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Diretor</p>
                        <p class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $current->name }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $current->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Como aparece no contrato</p>
                        {{-- A mesma composição do bloco do CONTRATANTE no documento. --}}
                        <div class="bg-white border border-gray-200 dark:border-gray-600 rounded-lg p-4 text-center">
                            @if($current->hasSignature())
                                <img src="{{ route('freelancer-director.signature', $current) }}" alt="Assinatura de {{ $current->name }}"
                                     class="mx-auto h-16 w-auto object-contain">
                            @else
                                <div class="h-16 flex items-center justify-center text-xs text-amber-600">sem imagem</div>
                            @endif
                            <div class="border-t border-gray-800 mt-1"></div>
                            <p class="text-sm font-extrabold text-gray-900 mt-1">CLUBE DOS FUNCIONARIOS DA CSN</p>
                            <p class="text-xs text-gray-500">CONTRATANTE</p>
                            <p class="text-[11px] text-gray-500 mt-1">Assinado digitalmente por {{ $current->name }} em dd/mm/aaaa às hh:mm</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ ALTERAR ============ --}}
        <form action="{{ route('freelancer-director.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $current ? 'Alterar cadastro' : 'Cadastrar diretor' }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Cada alteração vira um cadastro novo: os contratos já aprovados continuam com a assinatura de quem
                        os aprovou, e lotes já enviados continuam valendo para quem recebeu o e-mail.
                    </p>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome do diretor <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $current?->name) }}" required maxlength="255" class="{{ $input }}">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Sai no documento: "Assinado digitalmente por …".</p>
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">E-mail <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $current?->email) }}" required maxlength="255" class="{{ $input }}">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Recebe os códigos de aprovação e recusa de cada lote.</p>
                        @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">
                            Imagem da assinatura (PNG)
                            @if($semAssinatura)<span class="text-red-500">*</span>@endif
                        </label>
                        <input type="file" name="signature" accept="image/png" @required($semAssinatura) id="signatureInput"
                               class="block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-bold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                            PNG de até {{ $maxKb }} KB, de preferência com fundo transparente e recortado rente ao traço.
                            @unless($semAssinatura) Deixe em branco para manter a imagem atual. @endunless
                        </p>
                        @error('signature')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        <div id="signaturePreview" class="hidden mt-3 inline-block bg-white border border-gray-200 rounded-lg p-3">
                            <img alt="Prévia da nova assinatura" class="h-16 w-auto object-contain">
                        </div>
                    </div>
                </div>

                <div class="px-6 pb-6 flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition">
                        Salvar cadastro
                    </button>
                </div>
            </div>
        </form>

        {{-- ============ HISTÓRICO ============ --}}
        @if($history->isNotEmpty())
            <div class="mt-8 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Cadastros anteriores</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Continuam valendo para os contratos que assinaram.</p>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($history as $old)
                        <div class="flex items-center justify-between gap-6 px-6 py-4">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900 dark:text-white">{{ $old->name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $old->email }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $old->created_at?->format('d/m/Y H:i') }}
                                    @if($old->createdBy) · por {{ $old->createdBy->name }} @elseif(!$old->created_by) · importado do .env @endif
                                </p>
                            </div>
                            @if($old->hasSignature())
                                <img src="{{ route('freelancer-director.signature', $old) }}" alt="Assinatura de {{ $old->name }}"
                                     class="h-10 w-auto object-contain bg-white rounded border border-gray-200 p-1 shrink-0">
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

<script>
    // Prévia local da imagem escolhida, antes de salvar.
    document.getElementById('signatureInput')?.addEventListener('change', function () {
        const box = document.getElementById('signaturePreview');
        const file = this.files && this.files[0];

        if (!file) {
            box.classList.add('hidden');
            return;
        }

        box.querySelector('img').src = URL.createObjectURL(file);
        box.classList.remove('hidden');
    });
</script>
</x-app-layout>
