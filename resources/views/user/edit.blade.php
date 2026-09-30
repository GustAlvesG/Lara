<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Usuários') }}
        </h2>
    </x-slot>

@php
    $P = \App\Authorization\Permissions::class;
    $currentSectors = $user->sectors->mapWithKeys(fn ($s) => [$s->id => $s->pivot->role])->all();
    $auditLabels = [
        'sector.member_added' => 'entrou no setor',
        'sector.member_removed' => 'saiu do setor',
        'sector.member_role_changed' => 'mudou de papel no setor',
        'user.created' => 'usuário criado',
        'user.permissions_changed' => 'permissões individuais alteradas',
    ];
    $inputClass = 'w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white';
    $cardClass = 'bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden';
    $cardHead = 'p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50';
    $saveButton = 'inline-flex items-center px-5 py-2 bg-[#A00001] text-white rounded-lg font-bold shadow hover:bg-[#800000] transition';
@endphp

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('users.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div class="h-14 w-14 rounded-full bg-[#ff6961] dark:bg-[#A00001] text-2xl font-bold flex items-center justify-center text-black dark:text-white border-2 border-white dark:border-gray-600 shadow-sm">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <span class="absolute bottom-0 right-0 h-4 w-4 {{ $user->status_id == '1' ? 'bg-green-500' : 'bg-red-500' }} border-2 border-white dark:border-gray-800 rounded-full"></span>
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $user->name }}</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">{{ $user->email }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-6">

                {{-- Dados da conta --}}
                <form action="{{ route('users.update', $user->id) }}" method="POST" class="{{ $cardClass }}">
                    @csrf
                    @method('PUT')

                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados da conta</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Senha e PIN em branco mantêm os atuais.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome Completo</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="{{ $inputClass }}">
                            @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="{{ $inputClass }}">
                            @error('email')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="matricula" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Matrícula</label>
                            <input type="text" name="matricula" id="matricula" value="{{ old('matricula', $user->matricula ?? '') }}" maxlength="5" placeholder="Ex: 00123" class="{{ $inputClass }}">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Usada para vincular ao registro no Banco de Horas.</p>
                            @error('matricula')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Estado da Conta</label>
                            <select name="status" id="status" class="{{ $inputClass }}">
                                <option value="1" @selected($user->status_id == '1')>Ativo</option>
                                <option value="2" @selected($user->status_id == '2')>Inativo</option>
                            </select>
                        </div>

                        <div>
                            <label for="pin" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">PIN de assinatura (6 dígitos)</label>
                            <input type="text" name="pin" id="pin" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off" placeholder="••••••" class="{{ $inputClass }} tracking-[0.5em] font-mono">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Kiosk de contratos. {{ $user->hasPin() ? 'Já definido.' : 'Ainda não definido.' }}</p>
                            @error('pin')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nova Senha</label>
                            <input type="password" name="password" id="password" autocomplete="new-password" placeholder="••••••••" class="{{ $inputClass }}">
                            @error('password')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Confirmar Nova Senha</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••" class="{{ $inputClass }}">
                        </div>
                    </div>

                    <div class="px-6 pb-6 flex justify-end">
                        <button type="submit" class="{{ $saveButton }}">Salvar dados</button>
                    </div>
                </form>

                {{-- Setores --}}
                <form action="{{ route('users.sectors.update', $user->id) }}" method="POST" class="{{ $cardClass }}">
                    @csrf
                    @method('PUT')

                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Setores</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Estar no setor dá tudo o que o setor alcança. Coordenar dá, além disso, as permissões marcadas como "só coordenadores" e os cargos (aprovar lote, validar contrato…).</p>
                    </div>

                    <div class="px-6">
                        @include('user.partials.sectors-select', ['sectors' => $sectors, 'current' => $currentSectors])
                    </div>

                    <div class="px-6 py-6 flex justify-end">
                        <button type="submit" class="{{ $saveButton }}">Salvar setores</button>
                    </div>
                </form>

                {{-- Permissões individuais --}}
                <form action="{{ route('users.permissions.update', $user->id) }}" method="POST" class="{{ $cardClass }}">
                    @csrf
                    @method('PUT')

                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Permissões individuais</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Para o caso nominal: dar a esta pessoa algo que o setor dela não dá. Somam-se às do setor.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($catalog as $group => $permissions)
                            <fieldset>
                                <legend class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">{{ $group }}</legend>
                                <div class="space-y-1.5">
                                    @foreach($permissions as $name => $label)
                                        <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, old('permissions', $direct), true))
                                                class="mt-0.5 rounded border-gray-300 text-[#A00001] focus:ring-[#A00001]">
                                            <span>{{ $label }} <span class="text-[10px] font-mono text-gray-400">{{ $name }}</span></span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    <div class="px-6 pb-6 flex justify-end">
                        <button type="submit" class="{{ $saveButton }}">Salvar permissões</button>
                    </div>
                </form>
            </div>

            {{-- Acesso efetivo e histórico --}}
            <div class="space-y-6">
                <div class="{{ $cardClass }}">
                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Acesso efetivo</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">O que a pessoa alcança hoje e de onde vem.</p>
                    </div>

                    <div class="p-6">
                        @if($effective->hasFullAccess())
                            <div class="rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 p-4 text-sm text-red-800 dark:text-red-300">
                                <p class="font-bold">Acesso total</p>
                                <p>Via {{ implode(', ', $effective->fullAccessSectors) }}. Alcança todas as permissões — mas não os cargos, que dependem de coordenar o setor certo.</p>
                            </div>
                        @elseif($effective->permissions === [])
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma permissão. Enxerga só o que é de todo mundo logado (InfoClube, Avisos, Empresas, Monitor de Acesso).</p>
                        @else
                            <ul class="space-y-2">
                                @foreach($effective->permissions as $name)
                                    <li class="text-sm">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $P::group($name) }} · {{ $P::label($name) }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ implode(', ', array_unique($effective->sourcesOf($name))) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="{{ $cardClass }}">
                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Histórico de acesso</h2>
                    </div>
                    <div class="p-6">
                        @forelse($audit as $log)
                            <div class="text-sm py-2 border-b border-gray-50 dark:border-gray-700 last:border-0">
                                <p class="text-gray-900 dark:text-white">
                                    {{ $auditLabels[$log->action] ?? $log->action }}
                                    @if($log->sector) <span class="font-semibold">{{ $log->sector->name }}</span> @endif
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $log->created_at?->format('d/m/Y H:i') }} · por {{ $log->actor->name ?? 'sistema' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhuma mudança registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</x-app-layout>
