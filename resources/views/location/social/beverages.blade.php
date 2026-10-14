{{-- Catálogo de bebidas das reservas sociais. Preço e Código MV mudados aqui
     não alteram reservas já feitas: cada uma guarda a cópia do item. --}}
@php
    $item = $editing;
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Bebidas dos espaços sociais" :back="route('social-booking.index')">
            O que o sócio pode somar à locação. O Código MV é digitado à mão, para quem lança a venda no MV.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="rounded-card bg-surface p-5 shadow-card">
            <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">{{ $item ? 'Editar ' . $item->name : 'Nova bebida' }}</h2>
            <form method="POST" action="{{ $item ? route('social-booking.beverages.update', $item) : route('social-booking.beverages.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-5 sm:items-end">
                @csrf
                @if ($item) @method('PUT') @endif
                <div class="sm:col-span-2"><x-input-label for="name" value="Nome" /><x-text-input id="name" name="name" class="mt-1" :value="old('name', $item->name ?? '')" required maxlength="255" /></div>
                <div><x-input-label for="unit" value="Unidade" /><x-text-input id="unit" name="unit" class="mt-1" :value="old('unit', $item->unit ?? '')" maxlength="60" placeholder="Ex.: fardo 12 un." /></div>
                <div><x-input-label for="mv_code" value="Código MV" /><x-text-input id="mv_code" name="mv_code" class="mt-1 font-mono" :value="old('mv_code', $item->mv_code ?? '')" maxlength="60" /></div>
                <div><x-input-label for="price" value="Preço (R$)" /><x-text-input type="number" step="0.01" min="0" id="price" name="price" class="mt-1" :value="old('price', $item->price ?? '')" required /></div>
                <label class="flex items-center gap-2 text-sm font-semibold text-ink sm:col-span-2">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $item->active ?? true)) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                    Disponível para novas reservas
                </label>
                <div class="flex gap-2 sm:col-span-3 sm:justify-end">
                    @if ($item)<x-secondary-button-a href="{{ route('social-booking.beverages.index') }}">Cancelar</x-secondary-button-a>@endif
                    <x-primary-button>{{ $item ? 'Salvar' : 'Adicionar' }}</x-primary-button>
                </div>
            </form>
        </section>

        @if ($beverages->isEmpty())
            <x-empty-state icon="tag">Nenhuma bebida cadastrada.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Bebida</th>
                                <th class="px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Código MV</th>
                                <th class="px-5 py-3.5 text-right text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Preço</th>
                                <th class="px-5 py-3.5 text-center text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Situação</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($beverages as $beverage)
                                <tr>
                                    <td class="px-5 py-3.5"><p class="font-semibold text-ink">{{ $beverage->name }}</p><p class="text-xs text-ink-3">{{ $beverage->unit }}</p></td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $beverage->mv_code ?: '—' }}</td>
                                    <td class="px-5 py-3.5 text-right font-mono font-semibold">{{ $money($beverage->price) }}</td>
                                    <td class="px-5 py-3.5 text-center"><x-pill :kind="$beverage->active ? 'ok' : 'off'">{{ $beverage->active ? 'Ativa' : 'Inativa' }}</x-pill></td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <x-secondary-button-a size="sm" href="{{ route('social-booking.beverages.index', ['edit' => $beverage->id]) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                                        <form method="POST" action="{{ route('social-booking.beverages.destroy', $beverage) }}" class="inline">
                                            @csrf @method('DELETE')
                                            <x-danger-button size="sm">Remover</x-danger-button>
                                        </form>
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
