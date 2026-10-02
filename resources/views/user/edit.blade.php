<x-app-layout>
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
    $inputClass = 'w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink';
    $cardClass = 'bg-surface rounded-2xl shadow-pop border border-line overflow-hidden';
    $cardHead = 'p-6 border-b border-line bg-subtle';
    $saveButton = 'inline-flex items-center px-5 py-2 bg-grena text-white rounded-lg font-bold shadow-card hover:bg-grena-hover transition';
@endphp

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('users.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div class="h-14 w-14 rounded-full bg-grena-tint text-2xl font-bold flex items-center justify-center text-grena-ink shadow-card">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    <span class="absolute bottom-0 right-0 h-4 w-4 {{ $user->status_id == '1' ? 'bg-ok' : 'bg-danger' }} border-2 border-white rounded-full"></span>
                </div>
                <div>
                    <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $user->name }}</h1>
                    <p class="text-ink-2 font-medium">{{ $user->email }}</p>
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
                        <h2 class="text-lg font-bold text-ink">Dados da conta</h2>
                        <p class="text-xs text-ink-2">Senha e PIN em branco mantêm os atuais.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label for="name" class="block text-sm font-bold text-ink mb-1">Nome Completo</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="{{ $inputClass }}">
                            @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-bold text-ink mb-1">E-mail</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="{{ $inputClass }}">
                            @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="matricula" class="block text-sm font-bold text-ink mb-1">Matrícula</label>
                            <input type="text" name="matricula" id="matricula" value="{{ old('matricula', $user->matricula ?? '') }}" maxlength="5" placeholder="Ex: 00123" class="{{ $inputClass }}">
                            <p class="mt-1 text-xs text-ink-2">Usada para vincular ao registro no Banco de Horas.</p>
                            @error('matricula')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-bold text-ink mb-1">Estado da Conta</label>
                            <select name="status" id="status" class="{{ $inputClass }}">
                                <option value="1" @selected($user->status_id == '1')>Ativo</option>
                                <option value="2" @selected($user->status_id == '2')>Inativo</option>
                            </select>
                        </div>

                        <div>
                            <label for="pin" class="block text-sm font-bold text-ink mb-1">PIN de assinatura (6 dígitos)</label>
                            <input type="text" name="pin" id="pin" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off" placeholder="••••••" class="{{ $inputClass }} tracking-[0.5em] font-mono">
                            <p class="mt-1 text-xs text-ink-2">Kiosk de contratos. {{ $user->hasPin() ? 'Já definido.' : 'Ainda não definido.' }}</p>
                            @error('pin')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-bold text-ink mb-1">Nova Senha</label>
                            <input type="password" name="password" id="password" autocomplete="new-password" placeholder="••••••••" class="{{ $inputClass }}">
                            @error('password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-bold text-ink mb-1">Confirmar Nova Senha</label>
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
                        <h2 class="text-lg font-bold text-ink">Setores</h2>
                        <p class="text-xs text-ink-2">Estar no setor dá tudo o que o setor alcança. Coordenar dá, além disso, as permissões marcadas como "só coordenadores" e os cargos (aprovar lote, validar contrato…).</p>
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
                        <h2 class="text-lg font-bold text-ink">Permissões individuais</h2>
                        <p class="text-xs text-ink-2">Para o caso nominal: dar a esta pessoa algo que o setor dela não dá. Somam-se às do setor.</p>
                    </div>

                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($catalog as $group => $permissions)
                            <fieldset>
                                <legend class="text-xs font-bold uppercase tracking-wider text-ink-3 mb-2">{{ $group }}</legend>
                                <div class="space-y-1.5">
                                    @foreach($permissions as $name => $label)
                                        <label class="flex items-start gap-2 text-sm text-ink">
                                            <input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, old('permissions', $direct), true))
                                                class="mt-0.5 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                            <span>{{ $label }} <span class="text-[10px] font-mono text-ink-3">{{ $name }}</span></span>
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
                        <h2 class="text-lg font-bold text-ink">Acesso efetivo</h2>
                        <p class="text-xs text-ink-2">O que a pessoa alcança hoje e de onde vem.</p>
                    </div>

                    <div class="p-6">
                        @if($effective->hasFullAccess())
                            <div class="rounded-xl bg-danger-soft border border-danger/40 p-4 text-sm text-grena-ink">
                                <p class="font-bold">Acesso total</p>
                                <p>Via {{ implode(', ', $effective->fullAccessSectors) }}. Alcança todas as permissões — mas não os cargos, que dependem de coordenar o setor certo.</p>
                            </div>
                        @elseif($effective->permissions === [])
                            <p class="text-sm text-ink-2">Nenhuma permissão. Enxerga só o que é de todo mundo logado (InfoClube, Avisos, Empresas, Monitor de Acesso).</p>
                        @else
                            <ul class="space-y-2">
                                @foreach($effective->permissions as $name)
                                    <li class="text-sm">
                                        <p class="font-semibold text-ink">{{ $P::group($name) }} · {{ $P::label($name) }}</p>
                                        <p class="text-xs text-ink-2">{{ implode(', ', array_unique($effective->sourcesOf($name))) }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>

                <div class="{{ $cardClass }}">
                    <div class="{{ $cardHead }}">
                        <h2 class="text-lg font-bold text-ink">Histórico de acesso</h2>
                    </div>
                    <div class="p-6">
                        @forelse($audit as $log)
                            <div class="text-sm py-2 border-b border-line last:border-0">
                                <p class="text-ink">
                                    {{ $auditLabels[$log->action] ?? $log->action }}
                                    @if($log->sector) <span class="font-semibold">{{ $log->sector->name }}</span> @endif
                                </p>
                                <p class="text-xs text-ink-2">
                                    {{ $log->created_at?->format('d/m/Y H:i') }} · por {{ $log->actor->name ?? 'sistema' }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-ink-2">Nenhuma mudança registrada.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</x-app-layout>
