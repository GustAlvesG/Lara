<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Usuários') }}
        </h2>
    </x-slot>


<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <form action="{{ route('users.update', $user->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('users.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Editar Usuário</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Atualize as informações de conta e privilégios de acesso.</p>
                    </div>
                </div>
                
                <div class="flex gap-3">
                    <a href="{{ route('profile.edit') }}" class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl font-bold shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                        Guardar Alterações
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-100 dark:border-gray-700 text-center">
                        <div class="relative inline-block mb-4">

                            <div href="{{ route('users.edit', ['id' => $user['id']]) }}" class="h-24 w-24 rounded-full bg-[#ff6961] dark:bg-[#A00001] text-3xl font-bold flex items-center justify-center text-indigo-700 dark:text-white font-bold border-2 border-white dark:border-gray-600 shadow-sm">
                                                {{ substr($user['name'], 0, 1) }}
                            </div>
                            <span class="absolute bottom-1 right-1 h-6 w-6 {{ $user['status_id'] == '1' ? 'bg-green-500' : 'bg-red-500' }} border-4 border-white dark:border-gray-800 rounded-full"></span>
                        </div>
                        
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $user['name'] }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $user['email'] }}</p>

                        <div class="text-left">
                            <label for="status" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-1 tracking-wider">Estado da Conta</label>
                            <select name="status" id="status" class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-semibold text-gray-700 dark:text-gray-300">
                                <option value="1" {{ $user['status_id'] == '1' ? 'selected' : '' }}>Ativo</option>
                                <option value="2" {{ $user['status_id'] == '2' ? 'selected' : '' }}>Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-[#A00001] dark:bg-[#] rounded-2xl shadow-xl p-6 text-white">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 mr-2 text-white dark:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h3 class="font-bold uppercase text-xs tracking-widest text-white dark:text-white">Segurança</h3>
                        </div>
                        <p class="text-sm text-white dark:text-gray-300">
                            Ao alterar a senha, certifique-se de que o utilizador seja notificado. Recomenda-se o uso de pelo menos 8 caracteres.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados Pessoais</h2>
                        </div>

                        <div class="px-6 pb-6 grid grid-cols-1 md:grid-cols-2 gap-6 pt-6">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome Completo</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $user['name']) }}" required
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Endereço de E-mail</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $user['email']) }}" required
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            </div>

                            <div>
                                <label for="role_id" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Perfil de Acesso</label>
                                <select name="role_id" id="role_id" required
                                        class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition uppercase text-xs font-bold tracking-wider bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    @foreach($roles as $role)
                                        <option value="{{ $role['id'] }}" {{ $user['roles'][0]['id'] == $role['id'] ? 'selected' : '' }}>
                                            {{ $role['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Alterar Senha</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Deixe os campos vazios se não pretender alterar a senha atual.</p>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nova Senha</label>
                                <input type="password" name="password" id="password" autocomplete="new-password"
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Confirmar Nova Senha</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

</x-app-layout>
