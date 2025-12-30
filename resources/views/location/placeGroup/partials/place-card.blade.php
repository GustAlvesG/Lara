@php
    $imageUrl = Str::startsWith($place->image, 'http') 
        ? $place->image 
        : ($place->image ? asset('images/'. $place->image) : asset('images/defaultImage.jpg'));

    $statusColor = $place->status_id == 1 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
    $statusText = $place->status_id == 1 ? 'Ativo' : 'Inativo';
@endphp

<div class="bg-white dark:bg-gray-800 rounded-xl overflow-hidden shadow-2xl border border-gray-100 dark:border-gray-700 transform hover:scale-[1.02] transition duration-300 mb-4">
    
    <!-- Imagem de Destaque - Altura Compacta (h-36) -->
    <div class="h-36 overflow-hidden bg-gray-100">
        <img class="w-full h-full object-cover" 
             src="{{ $imageUrl }}" 
             alt="Imagem de {{ $place->name }}">
    </div>

    <!-- Conteúdo do Card -->
    <div class="p-3">
        
        <!-- Título com Link de Edição -->
        <a href="{{ route('place-group.editPlace', $place->id) }}" class="block mb-3">
            <h3 class="text-lg font-extrabold text-gray-900 dark:text-white hover:text-red-800 transition duration-150 leading-tight">
                {{ $place->name }}
            </h3>
        </a>

        <!-- Informações de Status e Valor -->
        <div class="space-y-1 mb-4">
            <div class="flex justify-between items-center text-sm">
                <span class="text-gray-500 dark:text-gray-400 font-medium">Status:</span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase {{ $statusColor }}">
                    {{ $statusText }}
                </span>
            </div>
            
            <div class="flex justify-between items-center text-sm border-t border-gray-50 pt-1">
                <span class="text-gray-500 dark:text-gray-400 font-medium">Valor:</span>
                <span class="text-indigo-700 dark:text-indigo-400 font-extrabold text-base">
                    R$ {{ number_format($place->price, 2, ',', '.') }}
                </span>
            </div>
        </div>

        <!-- Ações do Card (Grid de 2 Colunas) -->
        <div class="grid grid-cols-2 gap-3 border-t border-gray-100">
            <!-- Botão Editar -->
            <a href="{{ route('place-group.editPlace', $place->id) }}" 
               class="inline-flex justify-center items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold uppercase tracking-widest rounded-lg transition duration-150">
                Editar
            </a>

            <!-- Botão Excluir -->
            <form action="{{ route('place-group.destroyPlace', $place->id) }}" method="POST" class="w-full">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        onclick="return confirm('Tem certeza que deseja deletar?')"
                        class="w-full inline-flex justify-center items-center px-3 py-2 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold uppercase tracking-widest rounded-lg transition duration-150 border border-red-100">
                    Excluir
                </button>
            </form>
        </div>

    </div>
</div>