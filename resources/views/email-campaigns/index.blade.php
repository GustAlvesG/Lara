{{--
    Lista de campanhas do Disparo de E-mails. Tabela: o que se compara é a
    situação e o andamento (enviados de quantos).
--}}
@php
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint sm:w-auto';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Campanhas de e-mail">
            Monte o e-mail, importe a lista e envie. O envio acontece no servidor: pode fechar a tela depois de começar.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('email-campaigns.create') }}"><x-icon name="plus" /> Nova campanha</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar placeholder="Buscar por nome ou assunto" :filters="['status']">
            <x-slot:controls>
                <select name="status" class="{{ $selectClass }}" aria-label="Situação">
                    <option value="">Todas as situações</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($campaigns->isEmpty())
            <x-empty-state icon="mail">
                @if (array_filter($filters))
                    Nenhuma campanha com esses filtros.
                    <a href="{{ route('email-campaigns.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhuma campanha ainda.
                    <a href="{{ route('email-campaigns.create') }}" class="font-bold text-grena-ink hover:underline">Criar a primeira</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Campanha</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Enviados</th>
                                <th class="{{ $th }}">Criada por</th>
                                <th class="{{ $th }}">Criada em</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($campaigns as $campaign)
                                @php
                                    $percent = $campaign->recipients_count ? (int) floor($campaign->sent_count * 100 / $campaign->recipients_count) : 0;
                                @endphp
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}">
                                        <a href="{{ route('email-campaigns.show', $campaign) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $campaign->name }}</a>
                                        <p class="text-xs text-ink-3">{{ $campaign->subject }}</p>
                                    </td>
                                    <td class="{{ $td }}">
                                        <x-pill :kind="$campaign->statusPill()">{{ $campaign->statusLabel() }}</x-pill>
                                        @if ($campaign->status === \App\Models\EmailCampaign\EmailCampaign::STATUS_SCHEDULED && $campaign->scheduled_at)
                                            <p class="mt-1 text-xs text-ink-3">{{ $campaign->scheduled_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} min-w-[160px]">
                                        <p class="font-mono text-ink">{{ number_format($campaign->sent_count, 0, ',', '.') }} / {{ number_format($campaign->recipients_count, 0, ',', '.') }}</p>
                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-subtle">
                                            <div class="h-full rounded-full bg-grena" style="width: {{ $percent }}%"></div>
                                        </div>
                                        @if ($campaign->failed_count)
                                            <p class="mt-1 text-xs text-danger">{{ $campaign->failed_count }} falharam</p>
                                        @endif
                                    </td>
                                    <td class="{{ $td }} text-ink-2">{{ $campaign->author?->name ?? '—' }}</td>
                                    <td class="{{ $td }} whitespace-nowrap text-ink-2">{{ $campaign->created_at?->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $campaigns->links() }}
        @endif
    </x-page>
</x-app-layout>
