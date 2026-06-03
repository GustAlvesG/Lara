<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Usuários') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('users.store') }}" method="POST">
            @csrf

            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('users.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Novo Usuário</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Cadastre um novo membro e defina suas permissões de acesso.</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('users.index') }}" class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl font-bold shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                        Criar Usuário
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-100 dark:border-gray-700 text-center">
                        <div class="relative inline-block mb-4">
                            <div class="h-24 w-24 rounded-full bg-[#ff6961] dark:bg-[#A00001] text-3xl font-bold flex items-center justify-center text-black dark:text-white border-2 border-white dark:border-gray-600 shadow-sm">
                                ?
                            </div>
                            <span class="absolute bottom-1 right-1 h-6 w-6 bg-green-500 border-4 border-white dark:border-gray-800 rounded-full"></span>
                        </div>

                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Novo Usuário</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Preencha os dados ao lado</p>

                        <div class="text-left">
                            <label for="status" class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-1 tracking-wider">Estado da Conta</label>
                            <select name="status" id="status" class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-semibold text-gray-700 dark:text-gray-300">
                                <option value="1" selected>Ativo</option>
                                <option value="2">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-[#A00001] rounded-2xl shadow-xl p-6 text-white">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h3 class="font-bold uppercase text-xs tracking-widest">Segurança</h3>
                        </div>
                        <p class="text-sm text-white/80">
                            Crie uma senha temporária para o usuário. Recomenda-se que o mesmo altere sua senha no primeiro acesso.
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
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Endereço de E-mail</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="role_id" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Perfil de Acesso</label>
                                <select name="role_id" id="role_id" required
                                        class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition uppercase text-xs font-bold tracking-wider bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                    <option value="" disabled selected>Selecione um perfil</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role['id'] }}" {{ old('role_id') == $role['id'] ? 'selected' : '' }}>
                                            {{ $role['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Senha de Acesso</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Defina uma senha inicial para o novo usuário.</p>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Senha</label>
                                <input type="password" name="password" id="password" autocomplete="new-password" required
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                                @error('password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Confirmar Senha</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" required
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
