@php
    
    // Acessando os dados através de $item
    $imageUrl = str_contains($item->image, 'http') ? $item->image : ($item->image ? asset('images/'. $item->image) : '');
    
    // URL fallback caso a imagem principal não funcione
    $fallbackImage = 'https://placehold.co/600x400/1E3A8A/ffffff?text=' . urlencode($item['name']);
    
@endphp

<div class="elements">
    <!-- Card Principal com Efeito Hover -->
    <!-- Nota: O 'href="#"' deve ser substituído pelo link real para a página de detalhes/agendamento. -->
    <a href="{{ $routeElement }}" class="block transform hover:scale-[1.02] transition duration-300">
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
                
                <!-- Outros Campos Dinâmicos Podem Ser Adicionados Aqui -->
                {{ $slot ?? '' }}

            </div>
        </div>
    </a>
</div>