<x-app-layout :bootstrap-grid="false">
@php
    $labels = [
        'sector.member_added' => 'Entrou no setor',
        'sector.member_removed' => 'Saiu do setor',
        'sector.member_role_changed' => 'Mudou de papel no setor',
        'sector.permissions_changed' => 'Permissões do setor alteradas',
        'sector.full_access_changed' => 'Acesso total alterado',
        'sector.created' => 'Setor criado',
        'sector.deleted' => 'Setor excluído',
        'user.created' => 'Usuário criado',
        'user.permissions_changed' => 'Permissões individuais alteradas',
    ];
    $roleName = fn ($r) => ['coordinator' => 'coordenador', 'collaborator' => 'colaborador'][$r] ?? $r;
    $describe = function ($log) use ($roleName) {
        $d = $log->details ?? [];

        return match ($log->action) {
            'sector.member_added' => 'como ' . $roleName($d['role'] ?? null),
            'sector.member_removed' => 'era ' . $roleName($d['role'] ?? null),
            'sector.member_role_changed' => $roleName($d['de'] ?? null) . ' → ' . $roleName($d['para'] ?? null),
            'sector.full_access_changed' => ($d['full_access'] ?? false) ? 'ligado' : 'desligado',
            'sector.permissions_changed' => collect($d)->map(fn ($c, $name) => $name . ': ' . ($c['de'] ?? '—') . ' → ' . ($c['para'] ?? '—'))->implode('; '),
            'user.permissions_changed' => trim(
                (($d['added'] ?? []) ? '+ ' . implode(', ', $d['added']) : '') . ' '
                . (($d['removed'] ?? []) ? '− ' . implode(', ', $d['removed']) : '')
            ),
            'user.created' => 'pela tela ' . (($d['via'] ?? '') === 'meu-setor' ? 'Meu setor' : 'Usuários'),
            'sector.deleted' => $d['name'] ?? '',
            default => '',
        };
    };
@endphp

<x-page>
    <x-page-title title="Histórico de acesso" :back="route('sectors.index')">
        Toda mudança de setor, de permissão e de acesso total, com quem fez.
    </x-page-title>

    <x-search-bar placeholder="Quem fez, usuário ou setor" />

    <div class="overflow-hidden rounded-card bg-surface shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">
                        <tr>
                            <th class="px-5 py-3">Quando</th>
                            <th class="px-5 py-3">Quem fez</th>
                            <th class="px-5 py-3">O quê</th>
                            <th class="px-5 py-3">Usuário</th>
                            <th class="px-5 py-3">Setor</th>
                            <th class="px-5 py-3">Detalhe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap font-mono text-xs text-ink-2">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-5 py-3 text-ink">{{ $log->actor->name ?? 'sistema' }}</td>
                                <td class="px-5 py-3 font-semibold text-ink">{{ $labels[$log->action] ?? $log->action }}</td>
                                <td class="px-5 py-3 text-ink">{{ $log->user->name ?? ($log->user_id ? '#' . $log->user_id : '—') }}</td>
                                <td class="px-5 py-3 text-ink">{{ $log->sector->name ?? ($log->sector_id ? '#' . $log->sector_id : '—') }}</td>
                                <td class="px-5 py-3 text-xs text-ink-2">{{ $describe($log) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-ink-2">{{ filled(request('q')) ? 'Nenhuma mudança encontrada com essa busca.' : 'Nenhuma mudança registrada.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

    </div>

    @if($logs->hasPages())
        {{ $logs->links() }}
    @endif
</x-page>

</x-app-layout>
