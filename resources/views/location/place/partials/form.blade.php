<input type="hidden" name="place_group_id" value="{{ $place_group->id ?? $item->group->id ?? '' }}">

<div class="space-y-5">

    {{-- ─── Informações Básicas ──────────────────────────────────────── --}}
    <div class="rounded-2xl border border-line overflow-hidden">
        <div class="px-5 py-3 bg-subtle border-b border-line">
            <p class="text-[10px] font-black uppercase tracking-widest text-ink-3">
                Informações Básicas
            </p>
        </div>
        <div class="px-5 py-5 bg-surface grid grid-cols-1 sm:grid-cols-2 gap-4">

            {{-- Nome --}}
            <div class="sm:col-span-2">
                <label for="name" class="block text-sm font-semibold text-ink mb-1">
                    Nome do Local
                </label>
                <x-text-input name="name" id="name" class="w-full" value="{{ $item->name ?? '' }}" required/>
            </div>

            {{-- Status --}}
            <div>
                <label for="status_id" class="block text-sm font-semibold text-ink mb-1">
                    Status
                </label>
                <select name="status_id" id="status_id"
                    class="w-full px-3 py-2 border border-line-strong rounded-lg shadow-card
                           focus:border-grena focus:ring-grena-tint
                           bg-surface text-ink text-sm">
                    <option value="1" @if(isset($item) && $item->status_id == 1) selected @endif>Ativo</option>
                    <option value="2" @if(isset($item) && $item->status_id == 2) selected @endif>Inativo</option>
                </select>
            </div>

            {{-- Preço --}}
            <div>
                <label for="price" class="block text-sm font-semibold text-ink mb-1">
                    Preço
                </label>
                <x-text-input type="number" step="0.01" min="0.00" name="price" id="price" class="w-full"
                    value="{{ $item->price ?? '' }}" required/>
            </div>

        </div>
    </div>

    {{-- ─── Home Assistant ───────────────────────────────────────────── --}}
    @php
        $canManageHA = auth()->user()->can('home-assistant');
    @endphp
    <div class="rounded-2xl border overflow-hidden
        {{ $canManageHA ? 'border-grena/40' : 'border-line' }}">

        <div class="px-5 py-3 border-b flex items-center gap-2
            {{ $canManageHA
                ? 'bg-grena-tint border-grena/40'
                : 'bg-subtle border-line' }}">
            <svg class="w-4 h-4 {{ $canManageHA ? 'text-grena-ink' : 'text-ink-3' }}"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            <p class="text-[10px] font-black uppercase tracking-widest
                {{ $canManageHA ? 'text-grena-ink' : 'text-ink-3' }}">
                Home Assistant
            </p>
            @if (!$canManageHA)
                <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold
                             bg-line text-ink-2">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Restrito a TI
                </span>
            @endif
        </div>

        <div class="px-5 py-4 bg-surface">
            <label for="contactor_id" class="block text-sm font-semibold text-ink mb-1">
                Switch vinculado
            </label>
            <select name="contactor_id" id="contactor_id"
                @if (!$canManageHA) disabled @endif
                class="w-full px-3 py-2 border rounded-lg shadow-card text-sm transition
                       focus:border-grena focus:ring-grena-tint
                       {{ $canManageHA
                            ? 'border-line-strong bg-surface text-ink'
                            : 'border-line bg-subtle text-ink-3 cursor-not-allowed' }}">
                <option value="">— Nenhum —</option>
                @foreach($contactors as $contactor)
                    <option value="{{ $contactor->id }}"
                        @if(isset($item) && $item->contactor_id == $contactor->id) selected @endif>
                        {{ $contactor->name }} ({{ $contactor->entity_id }})
                    </option>
                @endforeach
            </select>
            @if (!$canManageHA)
                <p class="mt-1.5 text-xs text-ink-3">
                    Apenas o departamento de TI pode alterar este campo.
                </p>
            @endif
        </div>
    </div>

    {{-- ─── Autoatendimento do sócio ──────────────────────────────────── --}}
    {{--
        Sem a permissão de Home Assistant: quem decide se a quadra pode ser
        acesa pelo sócio é quem administra os espaços, não a TI. O que é
        restrito ali em cima é o switch, que é fiação.
    --}}
    <div class="rounded-2xl border border-line overflow-hidden">
        <div class="px-5 py-3 bg-subtle border-b border-line">
            <p class="text-[10px] font-black uppercase tracking-widest text-ink-3">
                Autoatendimento do sócio
            </p>
        </div>
        <div class="px-5 py-4 bg-surface">
            <label for="self_service_lighting" class="flex items-start gap-3 cursor-pointer">
                {{-- Campo oculto: checkbox desmarcado não é enviado, e sem ele desmarcar nunca gravaria. --}}
                <input type="hidden" name="self_service_lighting" value="0">
                <input type="checkbox" name="self_service_lighting" id="self_service_lighting" value="1"
                    @if(old('self_service_lighting', isset($item) ? $item->self_service_lighting : false)) checked @endif
                    class="mt-0.5 w-4 h-4 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                <span>
                    <span class="block text-sm font-semibold text-ink">
                        Permitir que o sócio acenda a luz pelo aplicativo
                    </span>
                    <span class="block mt-0.5 text-xs text-ink-2">
                        Vale só nos horários de uso livre (fim de semana e feriados liberados),
                        por tempo limitado e uma quadra por sócio. Precisa de um switch vinculado
                        acima — sem ele, a quadra não aparece no aplicativo.
                    </span>
                </span>
            </label>
        </div>
    </div>

    {{-- ─── Imagem ────────────────────────────────────────────────────── --}}
    <div class="rounded-2xl border border-line overflow-hidden">
        <div class="px-5 py-3 bg-subtle border-b border-line">
            <p class="text-[10px] font-black uppercase tracking-widest text-ink-3">
                Imagem
            </p>
        </div>
        <div class="px-5 py-5 bg-surface">
            <p class="text-sm text-ink-2 mb-4">
                Imagem para exibição no site.
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-start">
                <div>
                    <label for="image" style="cursor: pointer;"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-surface border border-line-strong
                               rounded-xl font-semibold text-xs text-ink uppercase tracking-widest shadow-card
                               hover:bg-subtle focus:outline-none focus:ring-2 focus:ring-grena-tint
                               focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Selecionar imagem
                    </label>
                    <input value="{{ $item->image ?? '' }}" type="file"
                        class="image-upload" name="image" id="image"
                        style="opacity: 0; position: absolute; z-index: -1;" />
                </div>
                <div>
                    @include('partials.imagePreview', ['id_preview' => 'image'])
                </div>
            </div>
        </div>
    </div>

</div>
