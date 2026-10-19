{{-- Uma linha da lista de convidados não sócios (nome + CPF). `$i` pode ser
     o marcador __I__ do <template>, trocado por script ao adicionar. --}}
<div class="flex flex-wrap items-center gap-2" data-guest-row>
    <input type="text" name="guests[{{ $i }}][name]" value="{{ $guest['name'] ?? '' }}" maxlength="255" placeholder="Nome completo"
        class="h-10 min-w-0 flex-1 rounded-xl border border-line-strong bg-surface px-3 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint" aria-label="Nome do convidado">
    <input type="text" name="guests[{{ $i }}][cpf]" value="{{ $guest['cpf'] ?? '' }}" maxlength="14" placeholder="CPF" inputmode="numeric"
        class="h-10 w-40 rounded-xl border border-line-strong bg-surface px-3 font-mono text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint" aria-label="CPF do convidado">
    <button type="button" data-remove-guest class="grid h-10 w-10 place-items-center rounded-full text-ink-3 hover:bg-danger-soft hover:text-danger" aria-label="Remover convidado">
        <x-icon name="trash" class="h-4 w-4" />
    </button>
</div>
