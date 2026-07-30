@php
    /**
     * Card de uma informação na listagem.
     *
     * A raiz (.elements) é o item direto do grid: é ela que o filter.js
     * esconde, então esconder o card remove a célula inteira em vez de deixar
     * um buraco na grade.
     *
     * Altura uniforme: o grid estica os itens (items-stretch) e aqui dentro o
     * card é flex-col com o rodapé preso por mt-auto, então blocos de conteúdo
     * de tamanhos diferentes não mudam a altura final.
     */
    $fallbackImage = 'https://placehold.co/600x400/7d0400/ffffff?text=' . urlencode($item->name);
    $imageUrl = $item->image ? asset('images/' . $item->image) : $fallbackImage;

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
        'Status' => $item->status,
        'Local' => $item->location,
    ], fn ($value) => filled($value));
@endphp

<div class="elements h-full">
    <div class="flex h-full flex-col overflow-hidden rounded-xl border border-gray-100 bg-white shadow transition duration-200 hover:-translate-y-0.5 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800">

        <a href="{{ route('information.show', $item->id) }}" class="block h-40 shrink-0 overflow-hidden bg-gray-200 dark:bg-gray-700">
            <img class="h-full w-full object-cover"
                 src="{{ $imageUrl }}"
                 onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                 alt="Imagem de {{ $item->name }}"
                 loading="lazy">
        </a>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <a href="{{ route('information.show', $item->id) }}" class="block">
                <h3 class="info-clamp-2 text-base font-extrabold leading-tight text-gray-900 hover:underline dark:text-white">
                    {{ $item->name }}
                </h3>
            </a>

            @if ($item->tags->isNotEmpty())
                <div class="flex flex-wrap gap-1">
                    @foreach ($item->tags as $tag)
                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700 dark:bg-red-900/30 dark:text-red-300">
                            #{{ $tag->name }}
                        </span>
                    @endforeach
                </div>
            @endif

            @if (filled($item->description))
                <div class="info-clamp-3 text-sm text-gray-500 dark:text-gray-400">
                    {!! $item->description !!}
                </div>
            @endif

            @if ($badges)
                <div class="flex flex-wrap gap-1">
                    @foreach ($badges as $label => $value)
                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                            {{ $label }}: {{ $value }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Rodapé preso na base: é o que mantém os cards alinhados. --}}
            <div class="mt-auto space-y-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                @if ($responsible)
                    <p class="truncate text-sm font-medium">
                        @if ($waLink)
                            <a href="{{ $waLink }}" target="_blank" rel="noopener"
                               class="text-[#7E1417] hover:underline dark:text-red-400">
                                {{ $responsible }}
                            </a>
                        @else
                            <span class="text-[#7E1417] dark:text-red-400">{{ $responsible }}</span>
                        @endif
                    </p>
                @endif

                <div class="text-lg font-extrabold text-[#7E1417] dark:text-red-400">
                    @if ($monthlyPrice !== null)
                        R$ {{ number_format($monthlyPrice, 2, ',', '.') }}
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-500">
                            {{ $firstPriceTitle !== '' ? $firstPriceTitle : 'Sócio' }}
                        </span>
                    @else
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-500">Consulte os valores</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
