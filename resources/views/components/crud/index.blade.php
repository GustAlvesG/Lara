<div class="py-6">
    <br>
    <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
        <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg page" data-limit="5" data-actual="">
            
            <!-- NOVA ESTRUTURA COM TAILWIND GRID (Solução) -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="elements-container">
                @foreach ($array as $item)
                    <!-- A classe 'col-span-1' é implícita no grid -->
                    <div> 
                        <x-crud.partials.element :item="$item" :routeElement="route('company.show', $item->id)" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
            <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                @include('partials.navPagination')
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/information/filter.js') }}"></script>