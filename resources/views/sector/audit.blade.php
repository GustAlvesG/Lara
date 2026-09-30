<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Setores') }}
        </h2>
    </x-slot>

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

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('sectors.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Histórico de acesso</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Toda mudança de setor, de permissão e de acesso total, com quem fez.</p>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-6 py-3">Quando</th>
                            <th class="px-6 py-3">Quem fez</th>
                            <th class="px-6 py-3">O quê</th>
                            <th class="px-6 py-3">Usuário</th>
                            <th class="px-6 py-3">Setor</th>
                            <th class="px-6 py-3">Detalhe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($logs as $log)
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-6 py-3 text-gray-900 dark:text-white">{{ $log->actor->name ?? 'sistema' }}</td>
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $labels[$log->action] ?? $log->action }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $log->user->name ?? ($log->user_id ? '#' . $log->user_id : '—') }}</td>
                                <td class="px-6 py-3 text-gray-700 dark:text-gray-300">{{ $log->sector->name ?? ($log->sector_id ? '#' . $log->sector_id : '—') }}</td>
                                <td class="px-6 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $describe($log) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">Nenhuma mudança registrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</div>

</x-app-layout>
