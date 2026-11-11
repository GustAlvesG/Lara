{{--
    Contratos (módulo próprio no menu desde 04/11/2026). Cada setor vê os seus; a Gerência vê
    todos e tem a aba "Para validar". Busca no servidor (SignatureContract::scopeSearch):
    número em qualquer grafia, título, setor, quem redigiu, partes e o texto.

    Desde 05/11/2026: aba "Em assinatura" (aprovados com o documento colhendo assinaturas) e a
    coluna "Assinatura" com o andamento (quantos assinaram e quem falta —
    SignatureContract::signingProgress). O presidente vê aqui também o que aguarda a assinatura dele.
    Aba "Arquivados" (05/11/2026): os arquivados só aparecem nela.
--}}
@php
    use App\Models\SignatureContract as C;

    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $kind = fn (string $s) => match ($s) {
        C::STATUS_IN_REVIEW => 'warn',
        C::STATUS_RETURNED => 'info',
        C::STATUS_APPROVED => 'ok',
        C::STATUS_CANCELED => 'danger',
        default => 'off',
    };
    $tab = 'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-[13px] font-bold text-ink-2 transition aria-selected:bg-surface aria-selected:text-ink aria-selected:shadow-card';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Contratos">
            Redigidos aqui, validados pela Gerência e assinados na aba Assinaturas. O cabeçalho e o rodapé (papel timbrado) e o presidente que assina vêm das configurações de Assinaturas.
            <x-slot:actions>
                @can('create', C::class)
                    <x-primary-button-a href="{{ route('contracts.create') }}"><x-icon name="plus" /> Novo contrato</x-primary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        {{-- Faixa do módulo (identidade de Contratos, 11/11/2026): os números das
             abas, cada um levando a ela. --}}
        @php
            $heroStats = [];
            if ($gerencia) {
                $heroStats[] = ['label' => 'Para validar', 'value' => $aguardando, 'href' => route('contracts.index', ['aba' => 'validar']), 'tone' => $aguardando > 0 ? 'warn' : null, 'current' => $aba === 'validar'];
            }
            $heroStats[] = ['label' => 'Em assinatura', 'value' => $emAssinatura, 'href' => route('contracts.index', ['aba' => 'assinatura']), 'current' => $aba === 'assinatura'];
            $heroStats[] = ['label' => 'Vencendo em 60 dias', 'value' => $vencendo, 'href' => route('contracts.index', ['aba' => 'vencendo']), 'tone' => $vencendo > 0 ? 'danger' : null, 'current' => $aba === 'vencendo'];
            $heroStats[] = ['label' => $aba === 'todos' ? 'Nesta lista' : 'Nesta aba', 'value' => $contracts->total()];
        @endphp
        <x-mod-hero variant="pen" eyebrow="Contratos" title="Do rascunho à assinatura" :stats="$heroStats" label="Resumo dos contratos">
            Redija, a Gerência valida e o documento segue para assinatura — tudo no mesmo lugar.
        </x-mod-hero>

        <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Contratos">
            <a href="{{ route('contracts.index') }}" role="tab" aria-selected="{{ $aba === 'todos' ? 'true' : 'false' }}" class="{{ $tab }}">Todos</a>
            @if($gerencia)
                <a href="{{ route('contracts.index', ['aba' => 'validar']) }}" role="tab" aria-selected="{{ $aba === 'validar' ? 'true' : 'false' }}" class="{{ $tab }}">
                    Para validar
                    @if($aguardando > 0)
                        <span class="rounded-full bg-grena px-2 text-[11px] text-white">{{ $aguardando }}</span>
                    @endif
                </a>
            @endif
            <a href="{{ route('contracts.index', ['aba' => 'assinatura']) }}" role="tab" aria-selected="{{ $aba === 'assinatura' ? 'true' : 'false' }}" class="{{ $tab }}">
                Em assinatura
                @if($emAssinatura > 0)
                    <span class="rounded-full bg-line px-2 text-[11px] text-ink">{{ $emAssinatura }}</span>
                @endif
            </a>
            {{-- Vencendo (05/11/2026): término em até 60 dias ou já vencido. --}}
            <a href="{{ route('contracts.index', ['aba' => 'vencendo']) }}" role="tab" aria-selected="{{ $aba === 'vencendo' ? 'true' : 'false' }}" class="{{ $tab }}">
                Vencendo
                @if($vencendo > 0)
                    <span class="rounded-full bg-warn px-2 text-[11px] text-white">{{ $vencendo }}</span>
                @endif
            </a>
            {{-- Arquivados (05/11/2026): fora das outras abas, nada muda no contrato. --}}
            <a href="{{ route('contracts.index', ['aba' => 'arquivados']) }}" role="tab" aria-selected="{{ $aba === 'arquivados' ? 'true' : 'false' }}" class="{{ $tab }}">Arquivados</a>
        </div>

        <x-search-bar :filters="['status', 'aba']" placeholder="Título, número, contratada, CNPJ, setor ou quem redigiu">
            <x-slot:controls>
                @if($aba === 'arquivados')
                    <input type="hidden" name="aba" value="arquivados">
                @endif
                @if(in_array($aba, ['todos', 'arquivados'], true))
                    <label for="status" class="sr-only">Situação</label>
                    <select name="status" id="status" class="{{ $field }} w-full sm:w-60">
                        <option value="">Todas as situações</option>
                        @foreach(C::STATUS_LABELS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="aba" value="{{ $aba }}">
                @endif
            </x-slot:controls>
        </x-search-bar>

        @if($contracts->isEmpty())
            <x-empty-state icon="doc">
                @if(filled($busca) || filled($status))
                    Nenhum contrato com esses filtros.
                    <a href="{{ route('contracts.index', $aba !== 'todos' ? ['aba' => $aba] : []) }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @elseif($aba === 'validar')
                    Nenhum contrato aguardando validação.
                @elseif($aba === 'assinatura')
                    Nenhum contrato colhendo assinaturas agora.
                @elseif($aba === 'arquivados')
                    Nenhum contrato arquivado.
                @elseif($aba === 'vencendo')
                    Nenhum contrato terminando nos próximos 60 dias.
                @else
                    Nenhum contrato por aqui ainda.
                    @can('create', C::class)
                        <a href="{{ route('contracts.create') }}" class="font-bold text-grena-ink hover:underline">Redigir o primeiro</a>.
                    @endcan
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Contrato</th>
                                <th class="{{ $th }}">Contratada</th>
                                <th class="{{ $th }}">Setor</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Assinatura</th>
                                <th class="{{ $th }}">Atualizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($contracts as $contract)
                                <tr class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('contracts.show', $contract) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $contract->title }}</a>
                                        <div class="text-xs text-ink-2">
                                            {{ $contract->number ?? 'Sem número' }}
                                            @if($contract->isAddendum()) · aditivo @endif
                                            · por {{ $contract->created_by_name ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-ink">{{ $contract->counterpartyName() ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $contract->sector_name ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <x-pill :kind="$kind($contract->status)">{{ $contract->statusLabel() }}</x-pill>
                                        @if($contract->expires_on && $contract->status !== C::STATUS_CANCELED)
                                            @php $dias = $contract->daysToExpiry(); @endphp
                                            <div class="mt-1 text-xs {{ $dias < 0 ? 'font-bold text-danger' : ($dias <= max(C::EXPIRY_ALERT_DAYS) ? 'font-bold text-warn' : 'text-ink-2') }}">
                                                {{ $dias < 0 ? 'Venceu em' : 'Vigente até' }} {{ $contract->expires_on->format('d/m/Y') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-xs">
                                        @include('signature.contracts.partials.signing-progress', ['contract' => $contract, 'compact' => true])
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink-2 whitespace-nowrap">{{ $contract->updated_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $contracts->links() }}
        @endif
    </x-page>
</x-app-layout>
