@php
    /**
     * Cartão de uma informação na listagem (refeito em 10/11/2026).
     *
     * O cartão inteiro é clicável pelo link do título (after:inset-0); o
     * WhatsApp do responsável fica por cima (z-10), num link próprio — link
     * dentro de link não é HTML válido.
     *
     * Sem foto (ou com o arquivo sumido), o x-media desenha o substituto na
     * cor do InfoClube com as iniciais — sem placehold.co.
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

    $tags = $item->tags->take(3);
    $moreTags = $item->tags->count() - $tags->count();
    $url = route('information.show', $item->id);
@endphp

<article class="group relative flex min-w-0 flex-col overflow-hidden rounded-card bg-surface shadow-card transition duration-200 hover:-translate-y-0.5 hover:shadow-pop focus-within:ring-4 focus-within:ring-grena-tint motion-reduce:hover:translate-y-0">
    <x-media :src="$imageUrl" :alt="'Imagem de ' . $item->name" area="info" :initials="$initials" logo ratio="sq" zoom>
        @if (filled($item->status))
            <x-media-tag side="right">{{ $item->status }}</x-media-tag>
        @endif
    </x-media>

    <div class="flex flex-1 flex-col gap-2 p-4 pt-3.5">
        @if ($tags->isNotEmpty())
            <p class="flex flex-wrap gap-x-2 gap-y-0.5 text-xs font-bold text-area-info-ink">
                @foreach ($tags as $tag)
                    <span>#{{ $tag->name }}</span>
                @endforeach
                @if ($moreTags > 0)
                    <span class="text-ink-3">+{{ $moreTags }}</span>
                @endif
            </p>
        @endif

        <h3 class="font-display text-[17px] font-semibold leading-snug tracking-tight text-ink">
            <a href="{{ $url }}" class="line-clamp-2 no-underline after:absolute after:inset-0 after:content-[''] focus:outline-none">{{ $item->name }}</a>
        </h3>

        {{-- Texto já sem HTML e escapado no controller (previewDescription). --}}
        @if (filled($item->description))
            <div class="line-clamp-2 text-sm text-ink-2">{!! $item->description !!}</div>
        @endif

        @if (filled($item->location) || filled($item->slots))
            <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-3">
                @if (filled($item->location))
                    <span class="inline-flex items-center gap-1"><x-icon name="home" class="h-3.5 w-3.5" />{{ $item->location }}</span>
                @endif
                @if (filled($item->slots))
                    <span class="inline-flex items-center gap-1"><x-icon name="users" class="h-3.5 w-3.5" />{{ $item->slots }} vagas</span>
                @endif
            </p>
        @endif

        <div class="mt-auto flex items-end justify-between gap-3 border-t border-line pt-3">
            <div class="min-w-0">
                @if ($monthlyPrice !== null)
                    <p class="font-mono text-lg font-semibold leading-none text-grena-ink">R$ {{ number_format($monthlyPrice, 2, ',', '.') }}</p>
                    <p class="mt-1 truncate text-xs text-ink-3">{{ $firstPriceTitle !== '' ? $firstPriceTitle : 'Sócio' }}</p>
                @else
                    <p class="text-[13px] text-ink-3">Consulte os valores</p>
                @endif
            </div>

            @if ($responsible)
                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" title="Falar com {{ $responsible }} no WhatsApp"
                       class="relative z-10 inline-flex min-h-9 min-w-0 max-w-[60%] items-center gap-1.5 rounded-full bg-ok-soft px-3 text-[13px] font-bold text-ok no-underline transition hover:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                        <x-icon name="chat" class="h-3.5 w-3.5" /><span class="truncate">{{ $responsible }}</span>
                    </a>
                @else
                    <span class="max-w-[60%] truncate text-[13px] text-ink-2">{{ $responsible }}</span>
                @endif
            @endif
        </div>
    </div>
</article>
