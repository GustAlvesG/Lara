<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Grupos e Permissões') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- HEADER -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Grupos e Permissões</h1>
                <p class="text-gray-500 font-medium">Gerencie os níveis de acesso e as capacidades de cada perfil do sistema.</p>
            </div>
            
            <button class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Grupo
            </button>
        </div>

        <!-- BARRA DE FILTRO -->
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-8 border border-gray-100">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" id="role-search-input" placeholder="Filtrar por nome do grupo ou permissão..." 
                       class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 outline-none shadow-sm"
                       onkeyup="filterRoles()">
            </div>
        </div>

        <!-- GRID DE GRUPOS -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="roles-container">


            @foreach($roles as $role)
            <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 flex flex-col role-card overflow-hidden transition-all duration-300 hover:shadow-indigo-100">
                <!-- Cabeçalho do Card -->
                <div class="p-6 border-b border-gray-50 flex items-center justify-between bg-gray-50/50">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-indigo-600 rounded-lg shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-extrabold text-gray-900 uppercase tracking-tight role-name">{{ $role['name'] }}</h2>
                            {{-- <p class="text-xs font-bold text-gray-400">GUARD: {{ $role['guard_name'] }}</p> --}}
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <button onclick="window.location.href='{{ route('roles-permission.show', $role['id']) }}'" class="p-2 text-indigo-600 hover:bg-white rounded-lg transition border border-transparent hover:border-indigo-100 hover:shadow-sm" title="Editar Permissões">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        </button>
                        <button class="p-2 text-red-700 hover:bg-white rounded-lg transition border border-transparent hover:border-red-100 hover:shadow-sm" title="Excluir Grupo">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- Lista de Permissões -->
                <div class="p-6 flex-grow">
                    <h3 class="text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        Permissões Ativas ({{ count($role['permissions']) }})
                    </h3>
                    
                    <div class="flex flex-wrap gap-2 permission-list">
                        @foreach($role['permissions'] as $permission)
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 cursor-help transition hover:bg-indigo-100" 
                                  title="{{ $permission['description'] }}">
                                {{ $permission['description'] }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Footer do Card -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 text-right">
                    <span class="text-xs font-medium text-gray-500">
                        ID do Grupo: #{{ $role['id'] }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
    /**
     * Filtra os cards de grupos com base no nome do grupo ou nas permissões
     */
    function filterRoles() {
        const input = document.getElementById('role-search-input');
        const filter = input.value.toUpperCase();
        const cards = document.querySelectorAll('.role-card');

        cards.forEach(card => {
            const roleName = card.querySelector('.role-name').textContent.toUpperCase();
            const permissions = card.querySelector('.permission-list').textContent.toUpperCase();
            
            const matches = roleName.includes(filter) || permissions.includes(filter);
            
            if (matches) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });
    }
</script>

</x-app-layout>
