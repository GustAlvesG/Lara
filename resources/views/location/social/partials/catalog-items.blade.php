{{-- Quantidades do catálogo (bebidas e mobiliário) num formulário de reserva.
     $catalog: itens ativos; $quantities: [id => quantidade] já escolhida;
     $kept: itens da reserva que saíram do catálogo (só na edição), que
     continuam editáveis pelo preço contratado. Cada linha leva preço e o que
     acrescenta de mesas/cadeiras, para o resumo estimado da tela. --}}
@php
    $quantities = collect($quantities ?? []);
    $kept = collect($kept ?? []);
    $byCategory = $catalog->groupBy('category');
    $row = 0;
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $input = 'catalog-qty h-10 w-24 rounded-xl border border-line-strong bg-surface px-3 text-right text-ink';
@endphp
@foreach (\App\Models\Social\SocialBeverage::CATEGORIES as $category => $label)
    <div class="{{ $loop->first ? '' : 'mt-5' }}">
        <p class="mb-2 text-xs font-black uppercase tracking-widest text-ink-3">{{ $label }}</p>
        @forelse ($byCategory[$category] ?? [] as $entry)
            <div class="flex items-center justify-between gap-3 border-b border-line py-2 last:border-0">
                <div>
                    <p class="font-semibold text-ink">{{ $entry->name }}</p>
                    <p class="text-xs text-ink-3">
                        {{ $entry->unit }}{{ $entry->unit ? ' · ' : '' }}{{ $money($entry->price) }}
                        @if ($entry->isFurniture()) · +{{ $entry->tables_per_unit }} mesa(s), +{{ $entry->chairs_per_unit }} cadeira(s) @endif
                        @if ($entry->max_quantity) · máx. {{ $entry->max_quantity }} @endif
                    </p>
                </div>
                <input type="hidden" name="items[{{ $row }}][beverage_id]" value="{{ $entry->id }}">
                <input type="number" min="0" max="{{ $entry->max_quantity ?: 999 }}" name="items[{{ $row }}][quantity]" value="{{ (int) ($quantities[$entry->id] ?? 0) }}"
                    data-price="{{ (float) $entry->price }}" data-tables="{{ (int) $entry->tables_per_unit }}" data-chairs="{{ (int) $entry->chairs_per_unit }}"
                    class="{{ $input }}" aria-label="Quantidade de {{ $entry->name }}">
            </div>
            @php $row++; @endphp
        @empty
            <p class="text-sm text-ink-3">Nenhum item ativo nesta categoria.</p>
        @endforelse
    </div>
@endforeach
@foreach ($kept as $item)
    <div class="flex items-center justify-between gap-3 border-b border-line py-2 last:border-0">
        <div>
            <p class="font-semibold text-ink">{{ $item->name }}</p>
            <p class="text-xs text-ink-3">fora do catálogo · {{ $money($item->unit_price) }}</p>
        </div>
        <input type="hidden" name="items[{{ $row }}][beverage_id]" value="{{ $item->social_beverage_id }}">
        <input type="number" min="0" max="999" name="items[{{ $row }}][quantity]" value="{{ $item->quantity }}"
            data-price="{{ (float) $item->unit_price }}" data-tables="{{ (int) $item->tables_per_unit }}" data-chairs="{{ (int) $item->chairs_per_unit }}"
            class="{{ $input }}" aria-label="Quantidade de {{ $item->name }}">
    </div>
    @php $row++; @endphp
@endforeach
