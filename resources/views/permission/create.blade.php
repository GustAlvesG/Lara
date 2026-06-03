<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Grupos e Permissões') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

    @include('partials.alerts')

    <form action="{{ route('roles-permission.store') }}" method="POST">
        @csrf

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('roles-permission.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Novo Grupo</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">Crie um novo perfil de acesso e defina suas permissões.</p>
                </div>
            </div>

            <div class="flex gap-3">
                <a href="{{ route('roles-permission.index') }}" class="px-6 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl font-bold shadow-md hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 transition">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition transform hover:scale-[1.02]">
                    Criar Grupo
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 border border-gray-100 dark:border-gray-700">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Informações</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">Nome do Perfil</label>
                            <input type="text" name="name" value="{{ old('name') }}" placeholder="ex: supervisor"
                                   class="w-full px-4 py-2 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-700 dark:text-gray-200 uppercase placeholder-gray-300 dark:placeholder-gray-600">
                            @error('name')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500">O nome do grupo é usado internamente para identificar o perfil. Use letras minúsculas, sem espaços.</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-800 dark:text-white">Permissões Disponíveis</h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Marque as caixas para conceder acesso às funcionalidades.</p>
                        </div>
                        <button type="button" onclick="toggleAll(this)"
                                class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            Selecionar todos
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($allPermissions as $perm)
                            <label class="relative flex items-start p-4 border-2 rounded-xl cursor-pointer transition hover:bg-gray-50 dark:hover:bg-gray-700/50 group border-gray-100 dark:border-gray-700 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 dark:has-[:checked]:bg-indigo-900/20 dark:has-[:checked]:border-indigo-500">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}"
                                           class="permission-checkbox h-5 w-5 text-indigo-600 border-gray-300 dark:border-gray-600 rounded focus:ring-indigo-500 transition cursor-pointer"
                                           {{ collect(old('permissions', []))->contains($perm['name']) ? 'checked' : '' }}>
                                </div>
                                <div class="ml-3 text-sm">
                                    <p class="font-bold text-gray-800 dark:text-gray-200 group-has-[:checked]:text-indigo-900 dark:group-has-[:checked]:text-indigo-300">{{ $perm['description'] }}</p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 font-mono">{{ $perm['name'] }}</p>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>
</div>

<script>
    function toggleAll(btn) {
        const checkboxes = document.querySelectorAll('.permission-checkbox');
        const allChecked = [...checkboxes].every(c => c.checked);
        checkboxes.forEach(c => c.checked = !allChecked);
        btn.textContent = allChecked ? 'Selecionar todos' : 'Desmarcar todos';
    }
</script>

</x-app-layout>
