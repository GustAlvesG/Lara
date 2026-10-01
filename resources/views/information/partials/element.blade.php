@php
    /**
     * Cartão de uma informação na listagem.
     *
     * Sem foto (ou com o arquivo sumido), o x-media desenha o substituto na
     * cor do InfoClube com as iniciais — sem placehold.co.
     *
     * Altura uniforme: o grid estica os itens e o rodapé do x-card fica preso
     * na base (mt-auto).
     */
    $imageUrl = $item->image ? asset('images/' . $item->image) : null;
    $nameParts = preg_split('/\s+/', trim($item->name), -1, PREG_SPLIT_NO_EMPTY);
    $initials = mb_strtoupper(collect($nameParts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));

    // Primeiro pacote de preço cadastrado (índice 0, não 1).
    $priceTitles = $item->name_price ? explode(';', $item->name_price) : [];
    $priceValues = $item->price_associated ? explode(';', $item->price_associated) : [];
    $firstPrice = $priceValues[0] ?? '';
    $firstPriceTitle = trim($priceTitles[0] ?? '');
    $monthlyPrice = $firstPrice !== '' ? (float) $firstPrice : null;

    // $item->responsible já vem como "Fulano, Beltrano" do controller.
    $responsible = $item->responsible !== '' ? $item->responsible : null;
    $contactDigits = $item->responsible_contact ? explode(';', $item->responsible_contact)[0] : '';
    $contactDigits = substr(preg_replace('/\D/', '', $contactDigits), 0, 11);
    $waLink = $contactDigits !== '' ? 'https://wa.me/55' . $contactDigits : null;

    $badges = array_filter([
        'Vagas' => $item->slots,
        'Local' => $item->location,
    ], fn ($value) => filled($value));
    $url = route('information.show', $item->id);
@endphp

<x-card :href="$url" class="transition hover:-translate-y-0.5">
    <x-slot:media>
        <x-media :src="$imageUrl" :alt="'Imagem de ' . $item->name" area="info" :initials="$initials" logo>
            @if (filled($item->status))
                <x-media-tag side="right">{{ $item->status }}</x-media-tag>
            @endif
        </x-media>
    </x-slot:media>

    <a href="{{ $url }}" class="text-[15.5px] font-bold leading-snug text-ink no-underline hover:underline">
        <span class="line-clamp-2">{{ $item->name }}</span>
    </a>

    @if ($item->tags->isNotEmpty())
        <div class="flex flex-wrap gap-1">
            @foreach ($item->tags as $tag)
                <span class="rounded-full bg-area-info px-2 py-0.5 text-xs font-semibold text-area-info-ink">#{{ $tag->name }}</span>
            @endforeach
        </div>
    @endif

    {{-- Texto já sem HTML e escapado no controller (previewDescription). --}}
    @if (filled($item->description))
        <div class="line-clamp-3 text-[13.5px] text-ink-2">{!! $item->description !!}</div>
    @endif

    @if ($badges)
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-3">
            @foreach ($badges as $label => $value)
                <span><b class="font-semibold text-ink-2">{{ $label }}:</b> {{ $value }}</span>
            @endforeach
        </div>
    @endif

    <x-slot:footer>
        <div class="min-w-0">
            @if ($monthlyPrice !== null)
                <b class="font-mono text-base font-semibold text-grena-ink">R$ {{ number_format($monthlyPrice, 2, ',', '.') }}</b>
                <span class="text-xs text-ink-3">{{ $firstPriceTitle !== '' ? $firstPriceTitle : 'Sócio' }}</span>
            @else
                <span class="text-[13px] text-ink-3">Consulte os valores</span>
            @endif
        </div>

        @if ($responsible)
            @if ($waLink)
                <a href="{{ $waLink }}" target="_blank" rel="noopener" title="Falar com {{ $responsible }} no WhatsApp"
                    class="inline-flex min-w-0 max-w-full items-center gap-1 truncate text-[13px] font-semibold text-grena-ink no-underline hover:underline">
                    <x-icon name="chat" class="h-3.5 w-3.5" /><span class="truncate">{{ $responsible }}</span>
                </a>
            @else
                <span class="truncate text-[13px] text-ink-2">{{ $responsible }}</span>
            @endif
        @endif
    </x-slot:footer>
</x-card>
