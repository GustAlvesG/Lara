{{--
    Contratos (Assinaturas → Contratos). Cada setor vê os seus; a Gerência vê
    todos e tem a aba "Para validar". Busca no servidor (SignatureContract::scopeSearch):
    número em qualquer grafia, título, setor, quem redigiu, partes e o texto.
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
            Redigidos aqui, validados pela Gerência e enviados para assinatura.
            <x-slot:actions>
                <x-secondary-button-a href="{{ route('signature-contract-library.index') }}">Biblioteca e modelos</x-secondary-button-a>
                @can('create', C::class)
                    <x-primary-button-a href="{{ route('signature-contracts.create') }}"><x-icon name="plus" /> Novo contrato</x-primary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($gerencia)
            <div class="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-subtle p-1" role="tablist" aria-label="Contratos">
                <a href="{{ route('signature-contracts.index') }}" role="tab" aria-selected="{{ $aba === 'todos' ? 'true' : 'false' }}" class="{{ $tab }}">Todos</a>
                <a href="{{ route('signature-contracts.index', ['aba' => 'validar']) }}" role="tab" aria-selected="{{ $aba === 'validar' ? 'true' : 'false' }}" class="{{ $tab }}">
                    Para validar
                    @if($aguardando > 0)
                        <span class="rounded-full bg-grena px-2 text-[11px] text-white">{{ $aguardando }}</span>
                    @endif
                </a>
            </div>
        @endif

        <x-search-bar :filters="['status', 'aba']" placeholder="Título, número, contratada, CNPJ, setor ou quem redigiu">
            <x-slot:controls>
                @if($aba === 'todos')
                    <label for="status" class="sr-only">Situação</label>
                    <select name="status" id="status" class="{{ $field }} w-full sm:w-60">
                        <option value="">Todas as situações</option>
                        @foreach(C::STATUS_LABELS as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="aba" value="validar">
                @endif
            </x-slot:controls>
        </x-search-bar>

        @if($contracts->isEmpty())
            <x-empty-state icon="doc">
                @if(filled($busca) || filled($status))
                    Nenhum contrato com esses filtros.
                    <a href="{{ route('signature-contracts.index', $aba === 'validar' ? ['aba' => 'validar'] : []) }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @elseif($aba === 'validar')
                    Nenhum contrato aguardando validação.
                @else
                    Nenhum contrato por aqui ainda.
                    @can('create', C::class)
                        <a href="{{ route('signature-contracts.create') }}" class="font-bold text-grena-ink hover:underline">Redigir o primeiro</a>.
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
                                <th class="{{ $th }}">Atualizado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($contracts as $contract)
                                <tr class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('signature-contracts.show', $contract) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $contract->title }}</a>
                                        <div class="text-xs text-ink-2">
                                            {{ $contract->number ?? 'Sem número' }}
                                            @if($contract->isAddendum()) · aditivo @endif
                                            · por {{ $contract->created_by_name ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-ink">{{ $contract->counterpartyName() ?? '—' }}</td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $contract->sector_name ?? '—' }}</td>
                                    <td class="px-5 py-3.5"><x-pill :kind="$kind($contract->status)">{{ $contract->statusLabel() }}</x-pill></td>
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
