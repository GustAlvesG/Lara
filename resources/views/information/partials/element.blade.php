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
    <a href="{{ route('information.show', $item['id']) }}" class="block transform hover:scale-[1.02] transition duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-700">

            <div class="h-36 overflow-hidden bg-gray-200 dark:bg-gray-700">
                <img class="w-full h-full object-cover" 
                    src="{{ $imageUrl }}" 
                    onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                    alt="Imagem de {{ $item['name'] }}"
                    style="max-height: 200px" 
                    >
            </div>
            
            <div class="p-4">
                
                <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mb-1 leading-tight card-title">
                    {{ $item['name'] }}
                </h3>
                
                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium mb-3">
                    {{-- 
                        Alteração Importante:
                        Removido style="color:#7E1417" e substituído por classes Tailwind:
                        text-[#7E1417] (cor original)
                        dark:text-red-400 (cor clara para fundo escuro)
                    --}}
                    <a href="{{ $wa_link }}" target="_blank" class="hover:underline text-[#7E1417] dark:text-red-600">
                            {{ $responsible }}
                        </a>
                </p>

                <div class="flex justify-between items-center border-t border-gray-100 dark:border-gray-700 pt-3">
                    
                    {{-- Código comentado mantido --}}

                    {{-- 
                        Substituído style="color: #7E1417" pelas classes:
                        text-[#7E1417] dark:text-red-400 
                    --}}
                    <div class="text-lg font-extrabold text-[#7E1417] dark:text-red-600">
                        R$ {{ number_format($monthlyPrice, 2, ',', '.') }} 
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-500">/ Mês (Sócio)</span>
                    </div>
                </div>

            </div>
        </div>
    </a>

</div>