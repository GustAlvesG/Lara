{{-- Administração › Integrações › <integração>: os tokens de cada
     instalação com o último uso (quando, de qual IP, em qual rota), gerar,
     rotacionar e revogar. O texto do token aparece uma vez, logo depois de
     gerado (sessão flash `new_api_token`). --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\ApiTokens\ApiIntegration;
    use App\Models\ApiTokens\ApiIntegrationToken as T;
    use App\Support\ApiTokens\ApiScopes;

    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $newToken = session('new_api_token');
    $statusPill = [
        T::STATUS_ACTIVE => ['ok', 'ativo'],
        T::STATUS_EXPIRING => ['warn', 'vence em breve'],
        T::STATUS_EXPIRED => ['danger', 'vencido'],
        T::STATUS_REVOKED => ['off', 'revogado'],
    ];
    $canManage = auth()->user()?->can(P::INTEGRACOES_GERENCIAR);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$integration->name" :back="route('api-integrations.index')">
            {{ $integration->systemLabel() }}
            @if (! $integration->active) · <strong>desativada: nenhum token dela passa</strong> @endif
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($newToken)
            <div class="flex flex-col gap-3 rounded-card border-2 border-warn bg-warn-soft p-5" x-data="{ copied: false }">
                <p class="font-bold text-ink">Token de "{{ $newToken['name'] }}": copie agora.</p>
                <p class="text-sm text-ink-2">Ele não aparece de novo: o Lara guarda só uma impressão dele. Se perder, rotacione.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-xl bg-surface px-3.5 py-2.5 font-mono text-sm text-ink" data-token>{{ $newToken['plain'] }}</code>
                    <x-secondary-button type="button" @click="navigator.clipboard.writeText($root.querySelector('[data-token]').textContent.trim()); copied = true">
                        <span x-show="!copied">Copiar</span><span x-show="copied" x-cloak>Copiado</span>
                    </x-secondary-button>
                </div>
                <p class="text-sm text-ink-2">No outro sistema, mande no header <span class="font-mono">Authorization: Bearer &lt;token&gt;</span>, no lugar do API_TOKEN antigo.</p>
            </div>
        @endif

        @if ($integration->notes)
            <p class="whitespace-pre-line rounded-card bg-surface p-5 text-sm text-ink-2 shadow-card">{{ $integration->notes }}</p>
        @endif

        <section class="flex flex-col gap-3">
            <h2 class="text-lg font-bold text-ink">Tokens</h2>

            @if ($integration->tokens->isEmpty())
                <x-empty-state icon="key">Nenhum token gerado. Gere um para cada instalação (produção, homologação…).</x-empty-state>
            @else
                <x-search-bar mode="client" target="#tokens" placeholder="Instalação, escopo, IP ou rota" />
                <div class="overflow-hidden rounded-card bg-surface shadow-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="tokens">
                            <thead>
                                <tr class="border-b border-line bg-subtle">
                                    <th class="{{ $th }}">Instalação</th>
                                    <th class="{{ $th }}">Escopos</th>
                                    <th class="{{ $th }}">Último uso</th>
                                    <th class="{{ $th }}">7 dias</th>
                                    <th class="{{ $th }}">Validade</th>
                                    <th class="{{ $th }}">Situação</th>
                                    @if ($canManage)<th class="{{ $th }}"></th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($integration->tokens as $token)
                                    @php([$kind, $label] = $statusPill[$token->status()])
                                    <tr data-search="{{ $token->name }} {{ implode(' ', $token->scopeLabels()) }} {{ $token->last_used_ip }} {{ $token->last_used_route }}" @class(['opacity-60' => $token->isRevoked() || $token->isExpired()])>
                                        <td class="px-5 py-3.5 align-top">
                                            <p class="font-semibold text-ink">{{ $token->name }}</p>
                                            <p class="font-mono text-xs text-ink-3">lara_{{ $token->token_hint }}</p>
                                            <p class="text-xs text-ink-3">gerado em {{ $token->created_at?->format('d/m/Y H:i') }}</p>
                                        </td>
                                        <td class="px-5 py-3.5 align-top text-ink-2">{{ implode(', ', $token->scopeLabels()) }}</td>
                                        <td class="px-5 py-3.5 align-top">
                                            @if ($token->last_used_at)
                                                <p class="font-semibold text-ink" title="{{ $token->last_used_at->format('d/m/Y H:i:s') }}">{{ $token->last_used_at->format('d/m/Y H:i') }}</p>
                                                <p class="text-xs text-ink-3">{{ $token->last_used_at->diffForHumans() }} · IP <span class="font-mono">{{ $token->last_used_ip }}</span></p>
                                                <p class="font-mono text-xs text-ink-3">{{ $token->last_used_route }}</p>
                                            @else
                                                <p class="text-ink-3">nunca usado</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 align-top text-ink-2">
                                            {{ number_format((int) ($hits[$token->id] ?? 0), 0, ',', '.') }}
                                            @if (($rejected[$token->id] ?? 0) > 0)
                                                <p class="text-xs text-danger">{{ number_format((int) $rejected[$token->id], 0, ',', '.') }} recusadas</p>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 align-top whitespace-nowrap text-ink-2">{{ $token->expires_at?->format('d/m/Y H:i') ?? 'sem validade' }}</td>
                                        <td class="px-5 py-3.5 align-top">
                                            <x-pill :kind="$kind">{{ $label }}</x-pill>
                                            @if ($token->isRevoked())
                                                <p class="mt-1 text-xs text-ink-3">em {{ $token->revoked_at->format('d/m/Y H:i') }}@if ($token->revoke_reason): {{ $token->revoke_reason }}@endif</p>
                                            @elseif ($token->replaced_by_id)
                                                <p class="mt-1 text-xs text-ink-3">substituído; vale até a validade</p>
                                            @endif
                                        </td>
                                        @if ($canManage)
                                            <td class="px-5 py-3.5 align-top">
                                                @unless ($token->isRevoked())
                                                    <details class="min-w-[16rem]">
                                                        <summary class="cursor-pointer text-sm font-semibold text-grena-ink">Rotacionar ou revogar</summary>
                                                        <form method="POST" action="{{ route('api-integrations.tokens.rotate', $token) }}" class="mt-3 flex flex-col gap-2 border-b border-line pb-3">
                                                            @csrf
                                                            <p class="text-xs text-ink-2">Gera um token novo com a mesma instalação e os mesmos escopos.</p>
                                                            @include('api-integrations.partials.validity', ['prefix' => 'rot-' . $token->id])
                                                            <x-input-label :for="'grace-' . $token->id" value="O antigo continua valendo por" />
                                                            <x-select-input :id="'grace-' . $token->id" name="grace_days">
                                                                @foreach ([0, 1, 7, 15, 30] as $days)
                                                                    <option value="{{ $days }}" @selected($days === $graceDays)>{{ $days === 0 ? 'nada (revoga já)' : $days . ' dia(s)' }}</option>
                                                                @endforeach
                                                            </x-select-input>
                                                            <x-primary-button size="sm">Rotacionar</x-primary-button>
                                                        </form>
                                                        <form method="POST" action="{{ route('api-integrations.tokens.revoke', $token) }}" class="mt-3 flex flex-col gap-2"
                                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('Revogar o token de ' . $token->name . '? O sistema que usa este token para de funcionar na hora.') }})">
                                                            @csrf
                                                            <x-text-input name="reason" maxlength="250" placeholder="Motivo (opcional)" />
                                                            <x-danger-button size="sm">Revogar agora</x-danger-button>
                                                        </form>
                                                    </details>
                                                @endunless
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        @if ($canManage)
            <details class="rounded-card bg-surface p-5 shadow-card" @if ($integration->tokens->isEmpty() || $errors->has('scopes') || $errors->has('validade')) open @endif>
                <summary class="cursor-pointer font-semibold text-ink">Gerar token</summary>
                <form method="POST" action="{{ route('api-integrations.tokens.store', $integration) }}" class="mt-3 flex flex-col gap-4">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="gen-name" value="Instalação" />
                            <x-text-input id="gen-name" name="name" class="mt-1" :value="old('name')" required maxlength="120" placeholder="Ex.: produção, homologação" />
                            <p class="mt-1 text-xs text-ink-3">Um token por instalação: revogar uma não derruba a outra.</p>
                        </div>
                        @include('api-integrations.partials.validity', ['prefix' => 'gen'])
                    </div>
                    <fieldset class="flex flex-col gap-2">
                        <legend class="text-sm font-semibold text-ink">Escopos (o que o token alcança)</legend>
                        @foreach (ApiScopes::catalog() as $scope => [$scopeLabel, $scopeDescription])
                            <label class="flex items-start gap-2 text-sm text-ink-2">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}" class="mt-0.5 h-4 w-4 rounded border-line-strong text-grena-ink"
                                       @checked(in_array($scope, old('scopes', $suggestedScopes), true))>
                                <span><strong class="text-ink">{{ $scopeLabel }}</strong> — {{ $scopeDescription }}</span>
                            </label>
                        @endforeach
                    </fieldset>
                    <div><x-primary-button>Gerar token</x-primary-button></div>
                </form>
            </details>

            <details class="rounded-card bg-surface p-5 shadow-card">
                <summary class="cursor-pointer font-semibold text-ink">Editar integração</summary>
                <form method="POST" action="{{ route('api-integrations.update', $integration) }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="edit-name" value="Nome" />
                        <x-text-input id="edit-name" name="name" class="mt-1" :value="old('name', $integration->name)" required maxlength="120" />
                    </div>
                    <div>
                        <x-input-label for="edit-system" value="Sistema" />
                        <x-select-input id="edit-system" name="system" class="mt-1">
                            @foreach (ApiIntegration::SYSTEMS as $key => $label)
                                <option value="{{ $key }}" @selected(old('system', $integration->system) === $key)>{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="edit-notes" value="Observação" />
                        <textarea id="edit-notes" name="notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2.5 text-ink">{{ old('notes', $integration->notes) }}</textarea>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-ink-2 sm:col-span-2">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" @checked($integration->active) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                        Ativa (desmarcada, nenhum token desta integração passa, sem precisar revogar)
                    </label>
                    <div><x-primary-button>Salvar</x-primary-button></div>
                </form>
            </details>
        @endif
    </x-page>
</x-app-layout>
