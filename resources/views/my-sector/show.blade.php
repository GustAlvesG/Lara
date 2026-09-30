<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Meu setor') }}
        </h2>
    </x-slot>

@php
    $P = \App\Authorization\Permissions::class;
    $inputClass = 'w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white';
    $cardClass = 'bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden';
    $cardHead = 'p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50';
    $existing = session('existing_user');
@endphp

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $sector->name }}</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Você coordena este setor: pode colocar e tirar colaboradores e cadastrar gente nova.</p>
            </div>
            @if($coordinated->count() > 1)
                <form method="GET" onchange="window.location = this.querySelector('select').value">
                    <select class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-sm">
                        @foreach($coordinated as $other)
                            <option value="{{ route('my-sector.show', $other) }}" @selected($other->id === $sector->id)>{{ $other->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        {{-- O que entrar no setor dá --}}
        <div class="rounded-2xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-5 text-sm text-amber-900 dark:text-amber-200">
            <p class="font-bold mb-1">Quem entra no setor passa a alcançar:</p>
            @if($sector->full_access)
                <p><span class="font-bold">tudo no sistema</span> — este setor tem acesso total.</p>
            @else
                @php $forAll = $sector->permissions->filter(fn ($p) => ! $p->pivot->coordinators_only); @endphp
                @if($forAll->isEmpty())
                    <p>nada além do que é de todo mundo logado.</p>
                @else
                    <p>{{ $forAll->map(fn ($p) => $P::group($p->name) . ' · ' . $P::label($p->name))->implode('; ') }}.</p>
                @endif
            @endif
        </div>

        @if($existing)
            <div class="rounded-2xl border border-indigo-200 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-900/20 p-5 text-sm text-indigo-900 dark:text-indigo-200">
                <p class="font-bold">Essa pessoa já tem usuário: {{ $existing['name'] }} ({{ $existing['email'] }}).</p>
                @if($existing['deleted'])
                    <p>A conta foi excluída. Para reativá-la, fale com quem administra os usuários.</p>
                @elseif($existing['in_sector'])
                    <p>Ela já está neste setor — nada a fazer.</p>
                @else
                    <form method="POST" action="{{ route('my-sector.members.add', $sector) }}" class="mt-3">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $existing['id'] }}">
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 transition">
                            Colocar {{ $existing['name'] }} no setor
                        </button>
                    </form>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {{-- Colocar quem já tem usuário --}}
            <form action="{{ route('my-sector.members.add', $sector) }}" method="POST" class="{{ $cardClass }}">
                @csrf
                <div class="{{ $cardHead }}">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Colocar no setor</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Quem já tem usuário. Entra como colaborador.</p>
                </div>
                <div class="p-6 space-y-4">
                    <select name="user_id" required class="{{ $inputClass }}">
                        <option value="">Selecione um usuário...</option>
                        @foreach($candidates as $candidate)
                            <option value="{{ $candidate->id }}">
                                {{ $candidate->name }}@if($candidate->matricula) (Mat. {{ $candidate->matricula }})@endif — {{ $candidate->email }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg font-bold hover:bg-indigo-700 transition">Adicionar</button>
                </div>
            </form>

            {{-- Cadastrar gente nova --}}
            <form action="{{ route('my-sector.users.store', $sector) }}" method="POST" class="{{ $cardClass }}">
                @csrf
                <div class="{{ $cardHead }}">
                    <h2 class="text-lg font-bold text-gray-800 dark:text-white">Cadastrar pessoa nova</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">A conta nasce ativa e já dentro do setor. Se a pessoa já existir (mesmo e-mail, matrícula ou CPF), o sistema avisa em vez de criar outra.</p>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome completo</label>
                        <input type="text" name="name" value="{{ old('name') }}" required class="{{ $inputClass }}">
                        @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="{{ $inputClass }}">
                        @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Matrícula</label>
                        <input type="text" name="matricula" value="{{ old('matricula') }}" maxlength="5" class="{{ $inputClass }}">
                        @error('matricula')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">CPF</label>
                        <input type="text" name="cpf" value="{{ old('cpf') }}" maxlength="14" class="{{ $inputClass }}">
                        @error('cpf')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Senha inicial</label>
                        <input type="password" name="password" required autocomplete="new-password" class="{{ $inputClass }}">
                        @error('password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Confirmar senha</label>
                        <input type="password" name="password_confirmation" required class="{{ $inputClass }}">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="px-5 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">Cadastrar e colocar no setor</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Equipe --}}
        <div class="{{ $cardClass }}">
            <div class="{{ $cardHead }} flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Equipe</h2>
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $sector->users->count() }} membro(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-6 py-3">Nome</th>
                            <th class="px-6 py-3">Matrícula</th>
                            <th class="px-6 py-3">Função</th>
                            <th class="px-6 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($sector->users as $member)
                            <tr>
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $member->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $member->email }}</p>
                                </td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $member->matricula ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    {{ $member->pivot->role === 'coordinator' ? 'Coordenador' : 'Colaborador' }}
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if($member->pivot->role === 'collaborator')
                                        <form method="POST" action="{{ route('my-sector.members.remove', [$sector, $member]) }}"
                                              onsubmit="return confirm('Tirar {{ addslashes($member->name) }} do setor? A pessoa perde o que o setor alcança.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Tirar do setor</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</x-app-layout>
