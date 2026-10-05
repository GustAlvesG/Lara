<x-app-layout>
@php
    $inputClass = 'w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink';
    $cardClass = 'bg-surface rounded-2xl shadow-pop border border-line overflow-hidden';
    $cardHead = 'p-6 border-b border-line bg-subtle';
    $saveButton = 'inline-flex items-center px-5 py-2 bg-grena text-white rounded-lg font-bold shadow-card hover:bg-grena-hover transition';
    $auditLabels = [
        'sector.member_added' => 'entrou',
        'sector.member_removed' => 'saiu',
        'sector.member_role_changed' => 'mudou de papel',
        'sector.permissions_changed' => 'permissões do setor alteradas',
        'sector.full_access_changed' => 'acesso total alterado',
        'sector.created' => 'setor criado',
        'user.created' => 'usuário criado pelo coordenador',
    ];
@endphp

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="flex items-center gap-4">
            <a href="{{ route('sectors.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    {{ $sector->name }}
                    @if($sector->full_access)
                        <span class="align-middle ml-2 px-2 py-0.5 text-xs font-bold uppercase rounded bg-danger-soft text-danger">Acesso total</span>
                    @endif
                </h1>
                <p class="text-ink-2 font-medium">Dados do setor, membros e o que o setor alcança no sistema.</p>
            </div>
        </div>

        {{-- Dados do setor --}}
        <form action="{{ route('sectors.update', $sector->id) }}" method="POST" class="{{ $cardClass }}">
            @csrf
            @method('PUT')

            <div class="{{ $cardHead }}">
                <h2 class="text-lg font-bold text-ink">Dados do Setor</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-bold text-ink mb-1">Nome do Setor <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $sector->name) }}" required class="{{ $inputClass }}">
                    <p class="mt-1 text-xs text-ink-2">Deve corresponder ao departamento no Banco de Horas. Os cargos (Gerência, Comercial, Contabilidade, Diretoria) são reconhecidos por este nome.</p>
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="description" class="block text-sm font-bold text-ink mb-1">Descrição</label>
                    <input type="text" name="description" id="description" value="{{ old('description', $sector->description) }}" class="{{ $inputClass }}">
                    @error('description')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="md:col-span-2 rounded-xl border border-danger/40 bg-danger-soft/50 p-4">
                    <label class="flex items-start gap-3">
                        <input type="hidden" name="full_access" value="0">
                        <input type="checkbox" name="full_access" value="1" @checked(old('full_access', $sector->full_access))
                            class="mt-1 rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                        <span>
                            <span class="block font-bold text-ink">Acesso total</span>
                            <span class="block text-sm text-ink-2">Todos os membros alcançam todas as permissões do sistema. Não dá os cargos (aprovar lote, validar contrato, níveis da ordem de compra), que dependem de coordenar o setor certo.</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="px-6 pb-6 flex justify-end">
                <button type="submit" class="{{ $saveButton }}">Salvar dados</button>
            </div>
        </form>

        {{-- O que o setor alcança --}}
        <form action="{{ route('sectors.permissions.update', $sector->id) }}" method="POST" class="{{ $cardClass }}">
            @csrf
            @method('PUT')

            <div class="{{ $cardHead }}">
                <h2 class="text-lg font-bold text-ink">O que o setor alcança</h2>
                <p class="text-xs text-ink-2">
                    @if($sector->full_access)
                        O setor tem acesso total, então esta lista não muda nada enquanto a marca estiver ligada. Ela volta a valer se o acesso total for retirado.
                    @else
                        Para cada permissão: ninguém do setor, todos os membros, ou só os coordenadores.
                    @endif
                </p>
            </div>

            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
                @foreach($catalog as $group => $permissions)
                    <fieldset>
                        <legend class="text-xs font-bold uppercase tracking-wider text-ink-3 mb-2">{{ $group }}</legend>
                        <div class="divide-y divide-line">
                            @foreach($permissions as $name => $label)
                                @php
                                    $value = array_key_exists($name, $granted) ? ($granted[$name] ? 'coordinators' : 'all') : '';
                                    $value = old('permissions.' . $name, $value);
                                @endphp
                                <div class="flex items-center justify-between gap-3 py-2">
                                    <span class="text-sm text-ink">
                                        {{ $label }}
                                        <span class="block text-[10px] font-mono text-ink-3">{{ $name }}</span>
                                    </span>
                                    <select name="permissions[{{ $name }}]"
                                        class="w-40 shrink-0 px-2 py-1.5 border rounded-lg text-sm bg-surface text-ink focus:ring-2 focus:ring-grena-tint outline-none {{ $value ? 'border-ok' : 'border-line' }}">
                                        <option value="" @selected($value === '')>—</option>
                                        <option value="all" @selected($value === 'all')>Todos</option>
                                        <option value="coordinators" @selected($value === 'coordinators')>Só coordenadores</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <div class="px-6 pb-6 flex justify-end">
                <button type="submit" class="{{ $saveButton }}">Salvar permissões do setor</button>
            </div>
        </form>

        {{-- Adicionar membro --}}
        <div class="{{ $cardClass }}">
            <div class="{{ $cardHead }}">
                <h2 class="text-lg font-bold text-ink">Adicionar Membro</h2>
            </div>

            <form action="{{ route('sectors.users.add', $sector->id) }}" method="POST" class="p-6">
                @csrf

                <div class="flex flex-col md:flex-row gap-4 items-end">
                    <div class="flex-1">
                        <label for="user_id" class="block text-sm font-bold text-ink mb-1">Usuário</label>
                        <select name="user_id" id="user_id" required class="{{ $inputClass }}">
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
                        <label for="role" class="block text-sm font-bold text-ink mb-1">Função no Setor</label>
                        <select name="role" id="role" required class="{{ $inputClass }}">
                            <option value="collaborator">Colaborador</option>
                            <option value="coordinator">Coordenador</option>
                        </select>
                    </div>

                    <button type="submit" class="px-6 py-2 bg-grena text-white rounded-lg font-bold hover:bg-grena-hover transition whitespace-nowrap">
                        Adicionar
                    </button>
                </div>
            </form>
        </div>

        {{-- Lista de membros --}}
        <div class="{{ $cardClass }}">
            <div class="{{ $cardHead }} flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Membros do Setor</h2>
                <span class="text-sm font-medium text-ink-2">{{ $sector->users->count() }} membro(s)</span>
            </div>

            @if($sector->users->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhum membro vinculado a este setor.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-6 py-3">Usuário</th>
                                <th class="px-6 py-3">Matrícula</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($sector->users as $member)
                            @php $isCoordinator = $member->pivot->role === 'coordinator'; @endphp
                            <tr class="hover:bg-subtle transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full bg-grena-tint text-sm font-bold flex items-center justify-center text-grena-ink">
                                            {{ substr($member->name, 0, 1) }}
                                        </div>
                                        <div>
                                            @can(\App\Authorization\Permissions::USUARIOS_GERENCIAR)
                                                <a href="{{ route('users.edit', $member->id) }}" class="font-semibold text-ink hover:underline">{{ $member->name }}</a>
                                            @else
                                                <p class="font-semibold text-ink">{{ $member->name }}</p>
                                            @endcan
                                            <p class="text-xs text-ink-2">{{ $member->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-ink">
                                    {{ $member->matricula ?? '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($isCoordinator)
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-warn-soft text-warn border border-warn/40">
                                            Coordenador
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-grena-tint text-grena-ink border border-grena/40">
                                            Colaborador
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap space-x-3">
                                    <form method="POST" action="{{ route('sectors.users.add', $sector->id) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $member->id }}">
                                        <input type="hidden" name="role" value="{{ $isCoordinator ? 'collaborator' : 'coordinator' }}">
                                        <button type="submit" class="text-grena-ink hover:underline font-medium text-xs">
                                            {{ $isCoordinator ? 'Tornar colaborador' : 'Tornar coordenador' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('sectors.users.remove', [$sector->id, $member->id]) }}" class="inline"
                                          onsubmit="return confirm('Remover {{ addslashes($member->name) }} do setor?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-danger hover:text-grena-ink font-medium text-xs transition">
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

        {{-- Histórico --}}
        <div class="{{ $cardClass }}">
            <div class="{{ $cardHead }} flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Histórico de acesso</h2>
                <a href="{{ route('sectors.audit') }}" class="text-sm font-bold text-grena-ink hover:underline">Ver tudo</a>
            </div>
            <div class="p-6">
                @forelse($audit as $log)
                    <div class="text-sm py-2 border-b border-line last:border-0">
                        <p class="text-ink">
                            @if($log->user) <span class="font-semibold">{{ $log->user->name }}</span> @endif
                            {{ $auditLabels[$log->action] ?? $log->action }}
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

</x-app-layout>
