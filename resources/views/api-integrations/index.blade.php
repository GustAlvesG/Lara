{{-- Administração › Integrações: os sistemas externos que chamam a API do
     Lara, com o último uso dos tokens de cada um, e quem ainda chama com o
     API_TOKEN antigo do .env ou sem token. Ver gestao-tokens.md. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\ApiTokens\ApiIntegration;
    use App\Models\ApiTokens\ApiIntegrationToken;
    use App\Services\ApiTokens\ApiTokenGuard;
    use App\Support\ApiTokens\ApiScopes;

    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Integrações">
            Sistemas que chamam a API do Lara (site, HAL, portaria, Home Assistant, Telegram, Poli). Cada instalação tem o próprio token, com escopos e validade.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <x-search-bar placeholder="Nome da integração, instalação ou observação" />

        @can(P::INTEGRACOES_GERENCIAR)
            <details class="rounded-card bg-surface p-5 shadow-card" @if ($errors->has('name') || $errors->has('system')) open @endif>
                <summary class="cursor-pointer font-semibold text-ink">Cadastrar integração</summary>
                <form method="POST" action="{{ route('api-integrations.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                    @csrf
                    <div>
                        <x-input-label for="new-name" value="Nome" />
                        <x-text-input id="new-name" name="name" class="mt-1" :value="old('name')" required maxlength="120" placeholder="Ex.: Site de locação" />
                    </div>
                    <div>
                        <x-input-label for="new-system" value="Sistema" />
                        <x-select-input id="new-system" name="system" required class="mt-1">
                            @foreach (ApiIntegration::SYSTEMS as $key => $label)
                                <option value="{{ $key }}" @selected(old('system') === $key)>{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div><x-primary-button>Cadastrar</x-primary-button></div>
                    <div class="sm:col-span-3">
                        <x-input-label for="new-notes" value="Observação (opcional)" />
                        <textarea id="new-notes" name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 text-ink" placeholder="Quem cuida, onde está instalado">{{ old('notes') }}</textarea>
                    </div>
                </form>
            </details>
        @endcan

        @if ($integrations->isEmpty())
            <x-empty-state icon="key">
                {{ $search !== '' ? 'Nenhuma integração encontrada para a busca.' : 'Nenhuma integração cadastrada ainda. Enquanto isso, os sistemas continuam entrando com o API_TOKEN antigo.' }}
            </x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($integrations as $integration)
                    @php
                        $live = $integration->tokens->filter(fn ($t) => ! $t->isRevoked() && ! $t->isExpired());
                        $expiring = $live->filter(fn ($t) => $t->status() === ApiIntegrationToken::STATUS_EXPIRING)->count();
                        $lastUsed = $integration->lastUsedAt();
                    @endphp
                    <a href="{{ route('api-integrations.show', $integration) }}" class="flex flex-col gap-3 rounded-card bg-surface p-5 no-underline shadow-card transition hover:shadow-lg">
                        <div class="flex items-start gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-grena-tint text-grena-ink">
                                <x-icon name="key" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-base font-bold text-ink">{{ $integration->name }}</p>
                                <p class="text-sm text-ink-3">{{ $integration->systemLabel() }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if (! $integration->active)
                                <x-pill kind="off">desativada</x-pill>
                            @endif
                            <x-pill :kind="$live->isEmpty() ? 'warn' : 'ok'">{{ $live->count() }} {{ $live->count() === 1 ? 'token ativo' : 'tokens ativos' }}</x-pill>
                            @if ($expiring > 0)
                                <x-pill kind="warn">{{ $expiring }} vence em breve</x-pill>
                            @endif
                        </div>
                        <p class="text-sm text-ink-2">
                            Último uso:
                            @if ($lastUsed)
                                <span class="font-semibold" title="{{ $lastUsed->format('d/m/Y H:i:s') }}">{{ $lastUsed->diffForHumans() }}</span>
                            @else
                                <span class="font-semibold">nunca</span>
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>
        @endif

        <section class="flex flex-col gap-3">
            <div>
                <h2 class="text-lg font-bold text-ink">Chamadas sem token da tela (últimos 30 dias)</h2>
                <p class="text-sm text-ink-2">
                    Quem ainda entra com o <span class="font-mono">API_TOKEN</span> antigo do .env
                    (@if ($legacyEnabled) <strong>aceito</strong> @else <strong>desligado: recusado</strong> @endif)
                    ou chama o Monitor de Acesso sem token
                    (@if ($portariaEnforced) <strong>recusado</strong> @else <strong>aceito, só registrado</strong> @endif).
                    Esta lista precisa zerar antes de desligar o token antigo.
                </p>
            </div>

            @if ($unmanaged->isEmpty())
                <x-empty-state icon="check">Nenhuma chamada com o token antigo ou sem token nos últimos 30 dias.</x-empty-state>
            @else
                <div class="overflow-hidden rounded-card bg-surface shadow-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle">
                                    <th class="{{ $th }}">Tipo</th>
                                    <th class="{{ $th }}">Escopo da rota</th>
                                    <th class="{{ $th }}">IP</th>
                                    <th class="{{ $th }}">Última rota</th>
                                    <th class="{{ $th }}">Chamadas</th>
                                    <th class="{{ $th }}">Última vez</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($unmanaged as $row)
                                    <tr>
                                        <td class="px-5 py-3.5">
                                            @if ($row->kind === ApiTokenGuard::KIND_LEGACY)
                                                <x-pill kind="warn">token antigo</x-pill>
                                            @else
                                                <x-pill kind="danger">sem token</x-pill>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ collect(explode(',', $row->scope))->map(fn ($s) => ApiScopes::label($s))->implode(', ') }}</td>
                                        <td class="px-5 py-3.5 font-mono text-ink-2">{{ $row->ip }}</td>
                                        <td class="px-5 py-3.5 font-mono text-xs text-ink-2">{{ $row->last_route }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ number_format((int) $row->hits, 0, ',', '.') }}</td>
                                        <td class="px-5 py-3.5 whitespace-nowrap text-ink-2">{{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->format('d/m/Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </x-page>
</x-app-layout>
