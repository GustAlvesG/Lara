{{-- Cupons de desconto de um evento: lista com usos, novo cupom e edição. --}}
@php
    use App\Models\Ticket\TicketCoupon;

    $th = 'px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $select = 'mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink';
    $dt = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : '';
    $types = $event->types->pluck('name', 'id');
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Cupons — ' . $event->title" :back="route('tickets.show', $event)">
            O cupom abate de cada ingresso a que se aplica uma porcentagem do preço do lote ou um valor fixo (nunca abaixo de zero). Quem compra digita o código no site; no balcão, ele vai no formulário de emissão.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($event->coupons->isEmpty())
            <x-empty-state icon="tag">Nenhum cupom neste evento.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Código</th>
                                <th class="{{ $th }}">Desconto</th>
                                <th class="{{ $th }}">Vale para</th>
                                <th class="{{ $th }}">Janela</th>
                                <th class="{{ $th }}">Usos</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($event->coupons as $coupon)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="font-mono font-bold text-ink">{{ $coupon->code }}</span>
                                        @unless ($coupon->active)<x-pill kind="off">inativo</x-pill>@endunless
                                        @if ($coupon->description)<p class="text-xs text-ink-3">{{ $coupon->description }}</p>@endif
                                    </td>
                                    <td class="px-4 py-3 font-mono">{{ $coupon->valueLabel() }}</td>
                                    <td class="px-4 py-3 text-ink-2">
                                        {{ TicketCoupon::AUDIENCE_LABELS[$coupon->audience] ?? $coupon->audience }}
                                        <span class="block text-xs text-ink-3">{{ $coupon->ticket_type_ids ? collect($coupon->ticket_type_ids)->map(fn ($id) => $types[$id] ?? '?')->implode(', ') : 'Todos os tipos' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-ink-2">
                                        @if ($coupon->starts_at || $coupon->ends_at)
                                            {{ $coupon->starts_at?->format('d/m H:i') ?? '—' }} a {{ $coupon->ends_at?->format('d/m H:i') ?? '—' }}
                                        @else
                                            Sem prazo
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-mono">{{ $uses[$coupon->id] ?? 0 }}{{ $coupon->max_uses ? ' / ' . $coupon->max_uses : '' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('tickets.coupons.destroy', [$event, $coupon]) }}" class="inline" onsubmit="return confirm('Apagar o cupom {{ $coupon->code }}?')">
                                            @csrf @method('DELETE')
                                            <x-danger-button size="sm">Apagar</x-danger-button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="bg-subtle/40">
                                    <td colspan="6" class="px-4 py-3">
                                        <details>
                                            <summary class="cursor-pointer text-sm font-semibold text-ink-2">Editar {{ $coupon->code }}</summary>
                                            <form method="POST" action="{{ route('tickets.coupons.update', [$event, $coupon]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                                                @csrf @method('PUT')
                                                @include('tickets.partials.coupon-fields', ['key' => 'c' . $coupon->id, 'coupon' => $coupon])
                                                <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" name="active" value="1" @checked($coupon->active)> Ativo</label>
                                                <div class="sm:col-span-3 sm:text-right"><x-primary-button>Salvar cupom</x-primary-button></div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="rounded-card bg-surface p-5 shadow-card">
            <h3 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Novo cupom</h3>
            <form method="POST" action="{{ route('tickets.coupons.store', $event) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                @csrf
                @include('tickets.partials.coupon-fields', ['key' => 'new', 'coupon' => null])
                <div class="sm:col-span-4 sm:text-right"><x-primary-button>Criar cupom</x-primary-button></div>
            </form>
        </div>
    </x-page>
</x-app-layout>
