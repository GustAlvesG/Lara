@php
    $equipe = $equipe ?? null;
@endphp

<div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
    <div class="p-6 border-b border-line bg-subtle">
        <h2 class="text-lg font-bold text-ink">Dados da Equipe</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Nome <span class="text-danger">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $equipe?->nome) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
            @error('nome')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Nome curto</label>
            <input type="text" name="nome_curto" value="{{ old('nome_curto', $equipe?->nome_curto) }}"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
            <p class="mt-1 text-xs text-ink-3">Usado no placar quando o nome completo não cabe.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Cidade</label>
            <input type="text" name="cidade" value="{{ old('cidade', $equipe?->cidade) }}"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
        </div>

        @if($equipe)
        <div class="md:col-span-2">
            <label class="flex items-center gap-3 p-4 rounded-xl border border-line cursor-pointer hover:bg-subtle transition">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $equipe->ativo))
                    class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft">
                <span class="text-sm font-bold text-ink">Ativa</span>
            </label>
        </div>
        @endif
    </div>
</div>
