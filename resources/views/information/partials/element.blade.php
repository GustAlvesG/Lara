{{-- <x-base-element>
    <x-slot name="image">
        {{ $item->image ? asset('images/'. $item->image) : asset('images/defaultImage.jpg') }}
    </x-slot>

    <x-slot name="bodyElement">
        <a href="{{ route('information.show', $item->id) }}" class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $item->category ? $item->category . ': ' : null }} {{ $item->name }} {{ $item->responsible ? ' - ' . $item->responsible : null }}
        </a>
        <div class="row">
            <div class="col-6">
                <span class="date">Atualizado em {{ $item->created_at->format('d/m/Y') }} por {{ $item->user->name }}</span>
            </div>
        </div>

        <div class="row my-2">
            <div class="col-12">
                <p class="description italic" style="word-wrap: break-word; overflow-wrap:">{!! $item->description !!}</p>
            </div>
        </div>

        <div class="row">
            @if ($item->slots)
                <div class="col-4">
                    <strong class="slots">Vagas disponíveis:</strong> {{ $item->slots }}
                </div>  
            @endif
        </div>
        
        @if (count($item->prices))
        <strong class="price">Preços:</strong> 
        @foreach ($item->prices as $itemPrice)
        <div class="row">
            <div class="col">
                <span class="price">{{ $itemPrice }}</span>
            </div>
        </div>
        @endforeach
        @endif 
    </x-slot>

    <x-slot name="showMoreRoute">
        {{ route('information.show', $item->id) }}
    </x-slot>

    
</x-base-element> --}}


@php
    // Assumindo que o JSON é passado para esta view/componente como $item
    
    
    // Processamento dos Dados
    $fallbackImage = 'https://placehold.co/600x400/7d0400/ffffff?text=' . urlencode($item['name']);
    $imageUrl = $item['image'] ? asset('images/'. $item['image']) : $fallbackImage;
    $monthlyPrice = (float)($item['price_associated'] ? explode(';', $item['price_associated'])[1] : 0);
    $responsible = $item['responsible'] ?? 'Não informado';
    $contact = $item['responsible_contact'] ? explode(';', $item['responsible_contact'])[0] : 'Não disponível';
    $contact = preg_replace('/\D/', '', $contact);
    //Keep first first 11 digits for country code and area code
    $contact = substr($contact, 0, 11);
    $wa_link = 'https://wa.me/55' . $contact;

    // URL fallback (Substitua por um asset real se a URL for local)
@endphp

<div class="element-card">
    <!-- Card Principal (Tema Azul Escuro) -->
    <a href="{{ route('information.show', $item['id']) }}" class="block transform hover:scale-[1.02] transition duration-300">
        <div class="bg-white rounded-xl overflow-hidden shadow-2xl border border-gray-100">

            <!-- Imagem de Destaque - Altura reduzida -->
            <div class="h-36 overflow-hidden bg-gray-200">
                <!-- Nota: Substitua 'asset' se o caminho for http/https -->
                <img class="w-full h-full object-cover" 
                    src="{{ $imageUrl }}" 
                    onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                    alt="Imagem de {{ $item['name'] }}"
                    style="max-height: 200px" 
                    >
            </div>
            
            <!-- Conteúdo do Card -->
            <div class="p-4">
                
                <!-- Título Principal -->
                <h3 class="text-xl font-extrabold text-gray-900 mb-1 leading-tight card-title">
                    {{ $item['name'] }}
                </h3>
                
                <!-- Responsável -->
                <p class="text-sm text-gray-500 font-medium mb-3">
                    <a href="{{ $wa_link }}" target="_blank" class="hover:underline" style="color:#7E1417">
                            {{ $responsible }}
                        </a>
                </p>

                <!-- Resumo das Informações e Preço -->
                <div class="flex justify-between items-center border-t border-gray-100 pt-3">
                    
                    {{-- <!-- Informação Principal (Contato) -->
                    <div class="text-sm text-gray-700 font-medium flex items-center">
                        <!-- Ícone de Telefone -->
                        @if ($contact !== 'Não disponível')
                        <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 00.948-.684l1.498-4.493a1 1 0 011.902 0l1.498 4.493a1 1 0 00.948.684H19a2 2 0 012 2v10a2 2 0 01-2 2h-3.28a1 1 0 00-.948.684l-1.498 4.493a1 1 0 01-1.902 0l-1.498-4.493A1 1 0 005.72 17H3a2 2 0 01-2-2V5z"></path></svg>
                        <a href="{{ $wa_link }}" target="_blank" class="hover:underline" style="color:#7E1417">
                            {{ $contact }}
                        </a>
                        @endif
                    </div> --}}

                    <!-- Preço (Mensalidade em destaque) -->
                    <div class="text-lg font-extrabold" style="color: #7E1417">
                        R$ {{ number_format($monthlyPrice, 2, ',', '.') }} 
                        <span class="text-xs font-normal text-gray-500">/ Mês (Sócio)</span>
                    </div>
                </div>

            </div>
        </div>
    </a>

</div>