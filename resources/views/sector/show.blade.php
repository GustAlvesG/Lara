<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Setores') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        {{-- Editar dados do setor --}}
        <form action="{{ route('sectors.update', $sector->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('sectors.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $sector->name }}</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Edite os dados do setor e gerencie os membros.</p>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados do Setor</h2>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome do Setor <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $sector->name) }}" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Deve corresponder ao departamento no Banco de Horas.</p>
                        @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Descrição</label>
                        <input type="text" name="description" id="description" value="{{ old('description', $sector->description) }}"
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                        @error('description')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </form>

        {{-- Adicionar membro --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Adicionar Membro</h2>
            </div>

            <form action="{{ route('sectors.users.add', $sector->id) }}" method="POST" class="p-6">
                @csrf

                <div class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label for="user_id" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Usuário</label>
                        <select name="user_id" id="user_id" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="">Selecione um usuário...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">
                                    {{ $user->name }}
                                    @if($user->matricula) (Mat. {{ $user->matricula }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="w-full md:w-64">
                        <label for="role" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Função no Setor</label>
                        <select name="role" id="role" required
                            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
                            <option value="collaborator">Colaborador</option>
                            <option value="coordinator">Coordenador</option>
                        </select>
                    </div>

                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 transition whitespace-nowrap">
                        Adicionar
                    </button>
                </div>
            </form>
        </div>

        {{-- Lista de membros --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Membros do Setor</h2>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $sector->users->count() }} membro(s)</span>
            </div>

            @if($sector->users->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum membro vinculado a este setor.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Usuário</th>
                                <th class="px-6 py-3">Matrícula</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($sector->users as $member)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-[#ff6961] dark:bg-[#A00001] text-sm font-bold flex items-center justify-center text-black dark:text-white">
                                            {{ substr($member->name, 0, 1) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">{{ $member->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $member->matricula ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($member->pivot->role === 'coordinator')
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700">
                                            Coordenador
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 border border-blue-200 dark:border-blue-700">
                                            Colaborador
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <form method="POST" action="{{ route('sectors.users.remove', [$sector->id, $member->id]) }}"
                                          onsubmit="return confirm('Remover {{ $member->name }} do setor?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 font-medium text-xs transition">
                                            Remover
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
</div>

</x-app-layout>
