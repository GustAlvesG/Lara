<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Usuários') }}
        </h2>
    </x-slot>

    <div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Gestão de Usuários</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Controle o acesso e as permissões dos membros do sistema.</p>
                </div>
                
                <div>
                    <button onclick="window.location.href='{{ route('roles-permission.index') }}'" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                        Grupos e Permissões
                    </button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 mb-8 border border-gray-100 dark:border-gray-700">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <input type="text" id="user-search-input" placeholder="Pesquisar por nome, e-mail ou perfil..." 
                        class="w-full pl-10 pr-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-150 outline-none shadow-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:placeholder-gray-500"
                        onkeyup="filterUsers()">
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700" id="users-table">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Usuário</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Perfil</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Último Acesso</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">

                            @foreach($users as $user)
                            <tr class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/20 transition duration-150 user-row">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <a href="{{ route('users.edit', ['id' => $user['id']]) }}" class="h-10 w-10 rounded-full bg-[#ff6961] dark:bg-[#A00001] flex items-center justify-center text-black dark:text-white font-bold border-2 border-white dark:border-gray-600 shadow-sm">
                                                {{ substr($user['name'], 0, 1) }}
                                            </a>
                                        </div>
                                        <div class="ml-4">
                                            <a href="{{ route('users.edit', ['id' => $user['id']]) }}" class="text-sm font-bold text-gray-900 dark:text-white user-name">{{ $user['name'] }}</a>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 user-email">{{ $user['email'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 uppercase tracking-tight user-role">
                                        {{ $user['roles'][0]->name ?? 'Sem Perfil' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($user['status_id'] == '1')
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300">
                                            ● Ativo
                                        </span>
                                    @else
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300">
                                            ○ Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($user['last_login_at'])->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('users.edit', ['id' => $user['id']]) }}" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-gray-600 rounded-lg transition duration-150 shadow-sm border border-gray-100 dark:border-gray-600 bg-white dark:bg-gray-700" title="Editar Usuário">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        {{-- <button class="p-2 text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-600 rounded-lg transition duration-150 shadow-sm border border-red-100 dark:border-gray-600 bg-white dark:bg-gray-700" title="Excluir Usuário" onclick="return confirm('Excluir este usuário permanentemente?')">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button> --}}
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    /**
     * Função de busca em tempo real para a tabela de usuários.
     */
    function filterUsers() {
        const input = document.getElementById('user-search-input');
        const filter = input.value.toUpperCase();
        const rows = document.querySelectorAll('.user-row');

        rows.forEach(row => {
            const name = row.querySelector('.user-name').textContent.toUpperCase();
            const email = row.querySelector('.user-email').textContent.toUpperCase();
            const role = row.querySelector('.user-role').textContent.toUpperCase();
            
            const matches = name.includes(filter) || email.includes(filter) || role.includes(filter);
            
            row.style.display = matches ? "" : "none";
        });
    }
</script>
</x-app-layout>
