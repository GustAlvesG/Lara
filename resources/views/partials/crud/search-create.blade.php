<div class="div">

    <div class="flex items-center"> 
    
            <input type="text" id="search-filter-text" placeholder="Pesquisar..." 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-base"
                    onkeyup="filterCards()">


        <x-primary-button-a 
            href="{{ $routeCreate }}"
            style="margin-left: 1rem !important;">
            {{ $buttonCreateText ?? __('Novo Registro') }}
        </x-primary-button-a>
            
    </div>
</div>
    
