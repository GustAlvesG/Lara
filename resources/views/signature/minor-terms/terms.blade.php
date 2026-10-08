{{--
    Assinaturas → Termo de Menores → Termos dos eventos. Um termo por evento:
    o modelo e a vigência (quando o tablet aceita termos novos). Um vigente por
    vez. Ver MinorTermController.
--}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Termo de Menores">
            O termo de cada evento e o período em que o tablet de autoatendimento aceita termos novos.
            @if($current)
                Vigente hoje: <span class="font-semibold text-ink">{{ $current->name }}</span>.
            @else
                Nenhum termo vigente hoje: o tablet mostra "não há termo disponível".
            @endif
        </x-page-title>

        @include('signature.minor-terms.partials.tabs', ['active' => 'terms'])
        @include('partials.alerts')

        <form action="{{ route('minor-terms.store') }}" method="POST" class="rounded-card bg-surface p-6 shadow-card">
            @csrf
            <h3 class="mb-4 font-display text-base font-semibold text-ink">Novo termo</h3>

            @include('signature.minor-terms.partials.form', ['term' => null])

            <div class="mt-5 flex justify-end">
                <x-primary-button>Cadastrar termo</x-primary-button>
            </div>
        </form>

        @if($terms->isEmpty())
            <x-empty-state icon="doc">Nenhum termo cadastrado ainda.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Evento</th>
                                <th class="{{ $th }}">Vigência</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Autorizados</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($terms as $term)
                                <tr class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5">
                                        <div class="font-semibold text-ink">{{ $term->name }}</div>
                                        <div class="text-xs text-ink-2">
                                            {{ $term->currentTemplate()?->name ?? 'Modelo não encontrado' }}
                                            @if($term->currentTemplate()) (v{{ $term->currentTemplate()->version }}) @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-ink-2">{{ $term->periodLabel() }}</td>
                                    <td class="px-5 py-3.5">
                                        <x-pill :kind="match ($term->situationLabel()) {
                                            'Vigente' => 'ok',
                                            'Agendado' => 'info',
                                            'Desativado' => 'danger',
                                            default => 'off',
                                        }">{{ $term->situationLabel() }}</x-pill>
                                    </td>
                                    <td class="px-5 py-3.5 font-mono text-sm text-ink">{{ $term->authorized_count }}</td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('minor-terms.edit', $term) }}">Editar</x-secondary-button-a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $terms->links() }}
        @endif
    </x-page>
</x-app-layout>
