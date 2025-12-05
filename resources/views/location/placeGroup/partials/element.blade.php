@php
    
    // Acessando os dados através de $item
    $imageUrl = str_contains($item->image_horizontal, 'http') ? $item->image_horizontal : ($item->image_horizontal ? asset('images/'. $item->image_horizontal) : asset('images/defaultImage.jpg'));
    $placeCount = count($item['places']);
    
    // Calcula o preço mínimo.
    $minPrice = collect($item['places'])->min('price') ?? '0.00';
    $minPrice = number_format((float)$minPrice, 2, ',', '.');
    
    // URL fallback caso a imagem principal não funcione
    $fallbackImage = 'https://placehold.co/600x400/1E3A8A/ffffff?text=' . urlencode($item['name']);
    
@endphp
<!-- Card Principal com Efeito Hover -->
<!-- Nota: O 'href="#"' deve ser substituído pelo link real para a página de detalhes/agendamento. -->
<a href="{{ route('place-group.show', $item->id) }}" class="block transform hover:scale-[1.02] transition duration-300">
    <div class="bg-white rounded-xl overflow-hidden shadow-2xl border border-gray-100">

        <!-- Imagem de Destaque - ALTURA REDUZIDA DE h-48 PARA h-36 -->
        <div class="h-36 overflow-hidden">
            <img class="w-full h-full object-cover" 
                 src="{{ $imageUrl }}" 
                 onerror="this.onerror=null;this.src='{{ $fallbackImage }}';"
                 alt="Imagem de {{ $item['name'] }}"
                 style="max-height: 200px">
        </div>
        
        <!-- Conteúdo do Card - PADDING REDUZIDO DE p-5 PARA p-4 -->
        <div class="p-4">
            
            <!-- Título Principal - TAMANHO REDUZIDO DE text-2xl PARA text-xl -->
            <h3 class="text-xl font-extrabold text-gray-900 mb-1 leading-tight">
                {{ $item['name'] }}
            </h3>
            
            <!-- Categoria / Tipo -->
            <p class="text-xs text-indigo-700 font-semibold mb-3">
                <span class="capitalize">{{ ucfirst($item['category']) }}</span>
            </p>

            <!-- Resumo das Informações -->
            <div class="flex justify-between items-center border-t border-gray-100 pt-3">
                
                <!-- Contagem de Quadras -->
                <div class="text-sm text-gray-700 font-medium flex items-center">
                    <!-- Ícone de Múltiplas Quadras/Lugares - Tamanho reduzido -->
                    <svg class="w-4 h-4 inline-block mr-1 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.723A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m4 18l-5.447-2.723A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m4 18V2m7 16l-5.447-2.723A1 1 0 0115 16.382V5.618a1 1 0 01.553-.894L21 5m-7 13.618l5.447 2.723A1 1 0 0121 20.382v-3.764a1 1 0 00-.553-.894L14 13.618m-7 0l5.447-2.723A1 1 0 0112 10.382V6.618a1 1 0 00-.553-.894L5 3m7 10.618V6.618m7 7l-5.447-2.723A1 1 0 0112 10.382V6.618a1 1 0 00-.553-.894L5 3"></path></svg>
                    {{ $placeCount }} Quadra(s)
                </div>

                <!-- Preço - TAMANHO REDUZIDO DE text-xl PARA text-lg -->
                <div class="text-lg font-extrabold text-green-600">
                    R$ {{ $minPrice }} 
                    <span class="text-xs font-normal text-gray-500">/hora</span>
                </div>
            </div>

        </div>
    </div>
</a>