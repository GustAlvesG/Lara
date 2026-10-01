<x-app-layout>
@php
    $input = 'w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink';
    $semAssinatura = !$current?->hasSignature();
@endphp

<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Diretoria</h1>
            <p class="text-ink-2 font-medium">
                Quem recebe o e-mail com os códigos do lote e assina, pelo CONTRATANTE, os contratos da redação 2.
            </p>
        </div>

        @include('freelancer.services.partials.tabs')
        @include('partials.alerts')

        @if(!$current)
            <div class="mb-6 p-4 bg-warn-soft border border-warn/40 text-warn rounded-lg text-sm font-medium">
                ⚠️ Nenhum diretor cadastrado. Enquanto não houver, nenhum lote pode ser enviado à diretoria.
            </div>
        @elseif($semAssinatura)
            <div class="mb-6 p-4 bg-warn-soft border border-warn/40 text-warn rounded-lg text-sm font-medium">
                ⚠️ Falta a <b>imagem da assinatura</b>. Sem ela, lotes com contratos da redação 2 não seguem para a
                diretoria — a assinatura do diretor é o que vai no documento quando ele aprova.
            </div>
        @endif

        {{-- ============ CADASTRO VIGENTE ============ --}}
        @if($current)
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden mb-8">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Cadastro vigente</h2>
                    <p class="text-xs text-ink-2">
                        Desde {{ $current->created_at?->format('d/m/Y H:i') }}
                        @if($current->createdBy) · por {{ $current->createdBy->name }} @elseif(!$current->created_by) · importado do .env @endif
                    </p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-ink-3">Diretor</p>
                        <p class="text-lg font-extrabold text-ink">{{ $current->name }}</p>
                        <p class="text-sm text-ink-2 mt-1">{{ $current->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-ink-3 mb-2">Como aparece no contrato</p>
                        {{-- A mesma composição do bloco do CONTRATANTE no documento. --}}
                        <div class="bg-surface border border-line rounded-lg p-4 text-center">
                            @if($current->hasSignature())
                                <img src="{{ route('freelancer-director.signature', $current) }}" alt="Assinatura de {{ $current->name }}"
                                     class="mx-auto h-16 w-auto object-contain">
                            @else
                                <div class="h-16 flex items-center justify-center text-xs text-warn">sem imagem</div>
                            @endif
                            <div class="border-t border-line-strong mt-1"></div>
                            <p class="text-sm font-extrabold text-ink mt-1">CLUBE DOS FUNCIONARIOS DA CSN</p>
                            <p class="text-xs text-ink-2">CONTRATANTE</p>
                            <p class="text-[11px] text-ink-2 mt-1">Assinado digitalmente por {{ $current->name }} em dd/mm/aaaa às hh:mm</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ============ ALTERAR ============ --}}
        <form action="{{ route('freelancer-director.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">{{ $current ? 'Alterar cadastro' : 'Cadastrar diretor' }}</h2>
                    <p class="text-xs text-ink-2">
                        Cada alteração vira um cadastro novo: os contratos já aprovados continuam com a assinatura de quem
                        os aprovou, e lotes já enviados continuam valendo para quem recebeu o e-mail.
                    </p>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Nome do diretor <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $current?->name) }}" required maxlength="255" class="{{ $input }}">
                        <p class="mt-1 text-xs text-ink-3">Sai no documento: "Assinado digitalmente por …".</p>
                        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">E-mail <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $current?->email) }}" required maxlength="255" class="{{ $input }}">
                        <p class="mt-1 text-xs text-ink-3">Recebe os códigos de aprovação e recusa de cada lote.</p>
                        @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">
                            Imagem da assinatura (PNG)
                            @if($semAssinatura)<span class="text-danger">*</span>@endif
                        </label>
                        <input type="file" name="signature" accept="image/png" @required($semAssinatura) id="signatureInput"
                               class="block w-full text-sm text-ink file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-bold file:bg-subtle file:text-ink hover:file:bg-line">
                        <p class="mt-1 text-xs text-ink-3">
                            PNG de até {{ $maxKb }} KB, de preferência com fundo transparente e recortado rente ao traço.
                            @unless($semAssinatura) Deixe em branco para manter a imagem atual. @endunless
                        </p>
                        @error('signature')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        <div id="signaturePreview" class="hidden mt-3 inline-block bg-surface border border-line rounded-lg p-3">
                            <img alt="Prévia da nova assinatura" class="h-16 w-auto object-contain">
                        </div>
                    </div>
                </div>

                <div class="px-6 pb-6 flex justify-end">
                    <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition">
                        Salvar cadastro
                    </button>
                </div>
            </div>
        </form>

        {{-- ============ HISTÓRICO ============ --}}
        @if($history->isNotEmpty())
            <div class="mt-8 bg-surface rounded-2xl shadow-card border border-line overflow-hidden">
                <div class="p-6 border-b border-line">
                    <h2 class="text-lg font-bold text-ink">Cadastros anteriores</h2>
                    <p class="text-xs text-ink-2">Continuam valendo para os contratos que assinaram.</p>
                </div>
                <div class="divide-y divide-line">
                    @foreach($history as $old)
                        <div class="flex items-center justify-between gap-6 px-6 py-4">
                            <div class="min-w-0">
                                <p class="font-bold text-ink">{{ $old->name }}</p>
                                <p class="text-sm text-ink-2">{{ $old->email }}</p>
                                <p class="text-xs text-ink-3">
                                    {{ $old->created_at?->format('d/m/Y H:i') }}
                                    @if($old->createdBy) · por {{ $old->createdBy->name }} @elseif(!$old->created_by) · importado do .env @endif
                                </p>
                            </div>
                            @if($old->hasSignature())
                                <img src="{{ route('freelancer-director.signature', $old) }}" alt="Assinatura de {{ $old->name }}"
                                     class="h-10 w-auto object-contain bg-surface rounded border border-line p-1 shrink-0">
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
