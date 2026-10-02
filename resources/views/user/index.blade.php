{{-- Usuários do painel. A lista vem inteira: a busca filtra na página. --}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Usuários">
            O acesso vem dos setores de cada pessoa e das permissões individuais.
            <x-slot:actions>
                @can(\App\Authorization\Permissions::SETORES_GERENCIAR)
                    <x-secondary-button-a href="{{ route('sectors.index') }}"><x-icon name="shield" /> Setores e permissões</x-secondary-button-a>
                @endcan
                <x-primary-button-a href="{{ route('users.create') }}"><x-icon name="user-plus" /> Novo usuário</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar mode="client" target="#usuarios" placeholder="Buscar por nome, e-mail, matrícula ou setor" />

        <div class="overflow-hidden rounded-card bg-surface shadow-card">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm" id="users-table">
                    <thead>
                        <tr class="border-b border-line">
                            <th class="{{ $th }}">Usuário</th>
                            <th class="{{ $th }}">Matrícula</th>
                            <th class="{{ $th }}">Setores</th>
                            <th class="{{ $th }}">Status</th>
                            <th class="{{ $th }}">Último acesso</th>
                            <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody id="usuarios" class="divide-y divide-line">
                        @foreach($users as $user)
                            @php
                                $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($user['name']), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                                $active = $user['status_id'] == '1';
                            @endphp
                            <tr data-search="{{ $active ? 'ativo' : 'inativo' }}" class="transition hover:bg-subtle">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <a href="{{ route('users.edit', ['id' => $user['id']]) }}" class="group flex items-center gap-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-grena-tint font-display text-sm font-semibold text-grena-ink" aria-hidden="true">{{ $initials }}</span>
                                        <span>
                                            <span class="block font-bold text-ink group-hover:text-grena-ink">{{ $user['name'] }}</span>
                                            <span class="block text-xs text-ink-2">{{ $user['email'] }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 font-mono text-ink-2">{{ $user['matricula'] ?? '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <div class="flex max-w-xs flex-wrap gap-1">
                                        @forelse($user->sectors->sortBy('name') as $sector)
                                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-bold {{ $sector->full_access ? 'bg-grena-tint text-grena-ink' : 'bg-subtle text-ink-2' }}"
                                                  title="{{ $sector->pivot->role === 'coordinator' ? 'Coordenador' : 'Colaborador' }}{{ $sector->full_access ? ' · acesso total' : '' }}">
                                                {{ $sector->name }}
                                                @if($sector->pivot->role === 'coordinator')
                                                    <x-icon name="star" class="h-3 w-3" /><span class="sr-only">coordenador</span>
                                                @endif
                                            </span>
                                        @empty
                                            <span class="text-xs text-ink-3">Sem setor</span>
                                        @endforelse
                                        @if($user->directPermissions->isNotEmpty())
                                            <span class="inline-flex rounded-full bg-subtle px-2.5 py-0.5 text-xs font-bold text-ink-2"
                                                  title="Permissões individuais">+{{ $user->directPermissions->count() }} individual</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <x-pill :kind="$active ? 'ok' : 'off'">{{ $active ? 'Ativo' : 'Inativo' }}</x-pill>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-ink-2">
                                    {{ $user['last_login_at'] ? \Carbon\Carbon::parse($user['last_login_at'])->diffForHumans() : '—' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-secondary-button-a size="sm" href="{{ route('users.edit', ['id' => $user['id']]) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                                        @if($user['id'] !== auth()->id())
                                            <form method="POST" action="{{ route('users.destroy', $user['id']) }}"
                                                  onsubmit="return confirm('Excluir o usuário \'{{ addslashes($user['name']) }}\' permanentemente?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" aria-label="Excluir {{ $user['name'] }}"
                                                        class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                    <x-icon name="trash" class="h-4 w-4" />
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </x-page>
</x-app-layout>
