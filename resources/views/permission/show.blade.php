<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Grupos e Permissões') }}
        </h2>
    </x-slot>



<div class="py-12 bg-gray-50 min-h-screen">
<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

    <form action="{{ route('roles-permission.update', $role['id']) }}" method="POST">
        @csrf
        @method('PUT')

        <!-- HEADER -->
        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('roles-permission.index') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Configurar Grupo</h1>
                    <p class="text-gray-500 font-medium">Defina o que o perfil <span class="text-indigo-600 uppercase">{{ $role['name'] }}</span> pode acessar.</p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <a href="{{ route('roles-permission.index') }}" class="px-6 py-3 bg-white text-gray-700 rounded-xl font-bold shadow-md hover:bg-gray-50 border border-gray-200 transition">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold shadow-lg hover:bg-indigo-700 transition transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Dados Básicos -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Informações</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Nome do Perfil</label>
                            <input type="text" name="name" value="{{ old('name', $role['name']) }}" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-gray-700 uppercase">
                        </div>
                        {{-- <div>
                            <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Guard Name</label>
                            <input type="text" name="guard_name" value="{{ $role['guard_name'] }}" readonly class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">
                        </div> --}}
                    </div>
                </div>
            </div>

            <!-- Mapa de Permissões (Checkboxes) -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 bg-gray-50/50">
                        <h2 class="text-lg font-bold text-gray-800">Permissões Disponíveis</h2>
                        <p class="text-sm text-gray-500">Marque as caixas para conceder acesso às funcionalidades.</p>
                    </div>

                    <div class="p-6 space-y-10">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($allPermissions as $perm)
                                <label class="relative flex items-start p-4 border-2 rounded-xl cursor-pointer transition hover:bg-gray-50 group border-gray-100 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm['name'] }}" 
                                               class="h-5 w-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition cursor-pointer"
                                               {{ collect(old('permissions', $selectedPermissions))->contains($perm['id']) ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <p class="font-bold text-gray-800 group-has-[:checked]:text-indigo-900">{{ $perm['description'] }}</p>
                                    </div>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>


</div>

</x-app-layout>
