{{-- Funções de freelancer. A lista vem inteira: a busca filtra na página. --}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Funções">
            Funções que um freelancer pode exercer, com valor por bloco de 15 minutos.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('freelancer-functions.create') }}"><x-icon name="plus" /> Nova função</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($functions->isEmpty())
            <x-empty-state icon="tag">
                Nenhuma função cadastrada.
                <a href="{{ route('freelancer-functions.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar a primeira</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#funcoes" placeholder="Buscar função ou descrição" />

            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">Descrição</th>
                                <th class="{{ $th }} text-right">Preço (15 min)</th>
                                <th class="{{ $th }} text-right">Serviços</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody id="funcoes" class="divide-y divide-line">
                            @foreach($functions as $function)
                            <tr data-search="" class="transition hover:bg-subtle">
                                <td class="px-5 py-3.5 font-semibold text-ink">
                                    <a href="{{ route('freelancer-functions.show', $function) }}" class="hover:text-grena-ink">{{ $function->name }}</a>
                                </td>
                                <td class="px-5 py-3.5 text-ink-2">{{ $function->description ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-right font-mono font-semibold text-ink">R$ {{ number_format($function->price, 2, ',', '.') }}</td>
                                <td class="px-5 py-3.5 text-right font-mono text-ink-2">{{ $function->freelancer_services_count }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-secondary-button-a size="sm" href="{{ route('freelancer-functions.show', $function) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                                        <form method="POST" action="{{ route('freelancer-functions.destroy', $function) }}"
                                              onsubmit="return confirm('Excluir a função \'{{ $function->name }}\'?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Excluir {{ $function->name }}"
                                                    class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>
