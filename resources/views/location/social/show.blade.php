{{-- Uma reserva de espaço social: dados, valor, contrato, decisão da equipe e
     lista de convidados. Cada ação só aparece no estágio em que cabe; a regra
     de verdade está em SocialBookingService (a tela só não oferece o que ele
     recusaria). --}}
@php
    $P = \App\Authorization\Permissions::class;
    $canDecide = auth()->user()->can($P::RESERVAS_SOCIAIS_APROVAR);
    $canRefund = auth()->user()->can($P::RESERVAS_PAGAMENTOS_ESTORNAR);
    $canRecordPayment = auth()->user()->can($P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL);
    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $payment = optional($booking->schedule)->schedulePayment;
    $refundable = $payment ? round((float) $payment->paid_amount - (float) $payment->refunded_amount, 2) : 0;
    $guestRows = old('guests', $booking->guests->map(fn ($g) => ['name' => $g->name, 'cpf' => $g->formattedCpf()])->all());
    $itemQty = $booking->items->pluck('quantity', 'social_beverage_id');
    $box = 'rounded-card bg-surface p-5 shadow-card';
    $h2 = 'mb-4 text-sm font-black uppercase tracking-widest text-ink-3';
    $textarea = 'mt-1 w-full rounded-xl border border-line-strong bg-surface px-3.5 py-2 text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Reserva social #' . $booking->id" :back="route('social-booking.index')">
            {{ optional($booking->place)->name }} · {{ $booking->event_date->locale('pt_BR')->isoFormat('dddd, DD/MM/YYYY') }}{{ $booking->timeLabel() ? ' · ' . $booking->timeLabel() : '' }}
            <x-slot:actions>
                <x-pill :kind="$booking->stageKind()">{{ $booking->stageLabel() }}</x-pill>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Reserva --}}
                <section class="{{ $box }}">
                    <h2 class="{{ $h2 }}">Reserva</h2>
                    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Sócio</dt><dd class="font-semibold text-ink">{{ optional($booking->member)->name ?? 'Não identificado' }} <span class="font-mono text-xs text-ink-3">{{ optional($booking->member)->title }}</span></dd></div>
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Contato</dt><dd class="text-ink">{{ optional($booking->member)->email ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Espaço</dt><dd class="font-semibold text-ink">{{ optional(optional($booking->place)->group)->name }} – {{ optional($booking->place)->name }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Evento</dt><dd class="text-ink">{{ $booking->event_type }}{{ $booking->expected_guests ? ' · ' . $booking->expected_guests . ' pessoas' : '' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Horário</dt><dd class="text-ink">{{ $booking->timeLabel() ?? 'Dia inteiro (espaço sem duração definida)' }}{{ $booking->duration_hours ? ' · ' . $booking->duration_hours . ' h' : '' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Mesas e cadeiras</dt><dd class="text-ink">{{ $booking->tablesTotal() }} mesa(s) · {{ $booking->chairsTotal() }} cadeira(s) <span class="text-xs text-ink-3">(base {{ (int) $booking->base_tables }} + {{ (int) $booking->base_chairs }})</span></dd></div>
                        @if ($booking->notes)
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-ink-3">Observações</dt><dd class="whitespace-pre-line text-ink">{{ $booking->notes }}</dd></div>
                        @endif
                        <div><dt class="text-xs font-bold uppercase text-ink-3">Pedida em</dt><dd class="text-ink">{{ $booking->created_at->format('d/m/Y H:i') }}{{ $booking->creator ? ' por ' . $booking->creator->name . ' (balcão)' : ' pelo app' }}</dd></div>
                        @if ($booking->payment_due_at)
                            <div><dt class="text-xs font-bold uppercase text-ink-3">Prazo para pagar</dt><dd class="font-semibold text-ink">{{ $booking->payment_due_at->format('d/m/Y H:i') }}</dd></div>
                        @endif
                        @if ($booking->approved_at)
                            <div><dt class="text-xs font-bold uppercase text-ink-3">Aprovada</dt><dd class="text-ink">{{ $booking->approved_at->format('d/m/Y H:i') }} por {{ optional($booking->approver)->name ?? '—' }}</dd></div>
                        @endif
                        @if ($booking->refused_at)
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-ink-3">Recusada</dt><dd class="text-ink">{{ $booking->refused_at->format('d/m/Y H:i') }} por {{ optional($booking->refuser)->name ?? '—' }}: {{ $booking->refusal_reason }}</dd></div>
                        @endif
                        @if ($booking->cancelled_at)
                            <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase text-ink-3">Cancelada</dt><dd class="text-ink">{{ $booking->cancelled_at->format('d/m/Y H:i') }} por {{ optional($booking->canceller)->name ?? 'sócio (app)' }}: {{ $booking->cancel_reason }}</dd></div>
                        @endif
                    </dl>
                </section>

                {{-- Editar a pré-reserva: tudo menos data e espaço, só em análise --}}
                @if ($booking->isEditable())
                    <details class="{{ $box }}" @if ($errors->any()) open @endif>
                        <summary class="cursor-pointer text-sm font-black uppercase tracking-widest text-ink-3">Editar pré-reserva (evento, extras e bebidas)</summary>
                        <p class="mt-2 text-xs text-ink-3">Data e espaço não mudam: para isso, cancele e faça outra pré-reserva. Convidados ficam no quadro abaixo. Depois do contrato ok, nada mais muda — nem aqui, nem pelo app.</p>
                        <form method="POST" action="{{ route('social-booking.details', $booking) }}" class="mt-4 space-y-4">
                            @csrf @method('PUT')
                            @if (optional($booking->place)->event_duration_hours)
                                <div>
                                    <x-input-label for="start_time" value="Horário de início ({{ $booking->place->event_duration_hours }} h de duração)" />
                                    <x-text-input type="time" id="start_time" name="start_time" class="mt-1 sm:w-40" :value="old('start_time', $booking->start_time ? substr($booking->start_time, 0, 5) : '')" required />
                                </div>
                            @endif
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div><x-input-label for="event_type" value="Tipo de evento" /><x-text-input id="event_type" name="event_type" class="mt-1" :value="old('event_type', $booking->event_type)" required maxlength="120" /></div>
                                <div><x-input-label for="expected_guests" value="Pessoas (total)" /><x-text-input type="number" min="1" id="expected_guests" name="expected_guests" class="mt-1" :value="old('expected_guests', $booking->expected_guests)" /></div>
                                <div><x-input-label for="extra_guests" value="Não sócios extras (máx. {{ (int) optional($booking->place)->extra_guests }})" /><x-text-input type="number" min="0" max="{{ (int) optional($booking->place)->extra_guests }}" id="extra_guests" name="extra_guests" class="mt-1" :value="old('extra_guests', $booking->extra_guests)" /></div>
                            </div>
                            <div><x-input-label for="notes" value="Observações" /><textarea id="notes" name="notes" rows="2" class="{{ $textarea }}">{{ old('notes', $booking->notes) }}</textarea></div>
                            <div>
                                <p class="mb-2 text-sm font-bold text-ink">Bebidas, mesas e cadeiras</p>
                                @include('location.social.partials.catalog-items', [
                                    'catalog' => $beverages,
                                    'quantities' => $itemQty,
                                    'kept' => $booking->items->whereNotIn('social_beverage_id', $beverages->pluck('id')->all()),
                                ])
                            </div>
                            <x-primary-button size="sm">Salvar alterações</x-primary-button>
                        </form>
                    </details>
                @endif

                {{-- Convidados --}}
                <section class="{{ $box }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-black uppercase tracking-widest text-ink-3">Convidados não sócios ({{ $booking->guests->count() }} de {{ $booking->guestLimit() }})</h2>
                        <x-secondary-button-a size="sm" href="{{ route('social-booking.guests.pdf', $booking) }}" target="_blank"><x-icon name="download" /> Lista para a portaria</x-secondary-button-a>
                    </div>
                    @if ($booking->stage === 'confirmed')
                        <p class="mb-3 text-xs text-ink-3">Reserva confirmada: cada convidado tem liberação pontual em {{ $booking->event_date->format('d/m/Y') }} no Monitor de Acesso.</p>
                    @endif
                    @if ($booking->isActive())
                        <form method="POST" action="{{ route('social-booking.guests', $booking) }}">
                            @csrf @method('PUT')
                            <div id="guests" class="space-y-2">
                                @foreach ($guestRows as $i => $guest)
                                    @include('location.social.partials.guest-row', ['i' => $i, 'guest' => $guest])
                                @endforeach
                            </div>
                            <template id="guest-template">@include('location.social.partials.guest-row', ['i' => '__I__', 'guest' => []])</template>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-secondary-button size="sm" id="add-guest"><x-icon name="plus" /> Convidado</x-secondary-button>
                                <x-primary-button size="sm">Salvar lista</x-primary-button>
                            </div>
                        </form>
                    @else
                        <ul class="divide-y divide-line text-sm">
                            @forelse ($booking->guests as $guest)
                                <li class="flex justify-between py-2"><span>{{ $guest->name }}</span><span class="font-mono text-ink-3">{{ $guest->maskedCpf() }}</span></li>
                            @empty
                                <li class="py-2 text-ink-3">Sem convidados.</li>
                            @endforelse
                        </ul>
                    @endif
                </section>
            </div>

            <aside class="space-y-6">
                {{-- Valor --}}
                <section class="{{ $box }}">
                    <h2 class="{{ $h2 }}">Valor</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-ink-2">Locação</dt><dd class="font-mono">{{ $money($booking->base_price) }}</dd></div>
                        @if ($booking->extra_guests > 0)
                            <div class="flex justify-between"><dt class="text-ink-2">{{ $booking->extra_guests }} extra(s) × {{ $money($booking->extra_guest_price) }}</dt><dd class="font-mono">{{ $money($booking->extras_total) }}</dd></div>
                        @endif
                        @foreach ($booking->items as $item)
                            <div class="flex justify-between gap-2"><dt class="text-ink-2">{{ $item->quantity }}× {{ $item->name }}@if ($item->mv_code) <span class="font-mono text-[11px] text-ink-3">MV {{ $item->mv_code }}</span>@endif</dt><dd class="font-mono">{{ $money($item->subtotal) }}</dd></div>
                        @endforeach
                        @if ((float) $booking->discount > 0)
                            <div class="flex justify-between text-ok"><dt>Desconto <span class="block text-xs text-ink-3">{{ $booking->discount_reason }}</span></dt><dd class="font-mono">− {{ $money($booking->discount) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-line pt-2 text-base font-bold"><dt>Total</dt><dd class="font-mono">{{ $money($booking->total) }}</dd></div>
                    </dl>
                    @if ($payment)
                        <p class="mt-3 text-xs text-ink-3">
                            Pago {{ $money($payment->paid_amount) }} em {{ optional($payment->paid_at)->format('d/m/Y H:i') }} · {{ $payment->methodLabel() }}
                            @if ($payment->is_manual) · baixa manual por {{ optional($payment->recorder)->name ?? '—' }} @endif
                            @if ((float) $payment->refunded_amount > 0) · estornado {{ $money($payment->refunded_amount) }} @endif
                            @can($P::RESERVAS_PAGAMENTOS) · <a class="font-bold text-grena-ink hover:underline" href="{{ route('payment.show', $payment->id) }}">ver pagamento</a>@endcan
                        </p>
                        @if ($payment->manual_notes)<p class="mt-1 text-xs text-ink-2">{{ $payment->manual_notes }}</p>@endif
                    @elseif ($booking->stage === 'awaiting_payment')
                        <p class="mt-3 text-xs text-ink-3">Aguardando o sócio pagar pelo site{{ $booking->payment_due_at ? ' até ' . $booking->payment_due_at->format('d/m/Y H:i') : '' }}.</p>
                    @endif
                </section>

                {{-- Baixa manual: pago fora da plataforma --}}
                @if (! $payment && $booking->stage === 'awaiting_payment' && $canRecordPayment)
                    <section class="{{ $box }}">
                        <h2 class="{{ $h2 }}">Baixa manual de pagamento</h2>
                        <p class="mb-3 text-xs text-ink-3">Para pagamento recebido fora da plataforma. Marca a reserva como paga pelo total ({{ $money($booking->total) }}): ela é confirmada, os convidados são liberados na portaria e o site deixa de cobrar.</p>
                        <form method="POST" action="{{ route('payment.manual') }}" class="space-y-3"
                              onsubmit="return confirm('Confirma que {{ $money($booking->total) }} foram recebidos fora da plataforma?');">
                            @csrf
                            <input type="hidden" name="schedule_ids[]" value="{{ $booking->schedule_id }}">
                            <input type="hidden" name="amount" value="{{ number_format((float) $booking->total, 2, '.', '') }}">
                            @include('payments.partials.manual-fields')
                            <x-primary-button size="sm">Marcar como pago</x-primary-button>
                        </form>
                    </section>
                @endif

                {{-- Contato com o sócio: N dias úteis depois da pré-reserva --}}
                @if ($booking->isActive())
                    <section class="{{ $box }}">
                        <h2 class="{{ $h2 }}">Contato com o sócio</h2>
                        @if ($booking->contacted_at)
                            <p class="text-sm text-ok"><x-icon name="check" class="inline h-4 w-4" /> Feito em {{ $booking->contacted_at->format('d/m/Y H:i') }} por {{ optional($booking->contactedBy)->name ?? '—' }}</p>
                            @if ($booking->contact_notes)<p class="mt-1 whitespace-pre-line text-xs text-ink-2">{{ $booking->contact_notes }}</p>@endif
                        @else
                            <p class="text-sm {{ $booking->contactOverdue() ? 'font-bold text-danger' : 'text-warn' }}">
                                {{ $booking->contactOverdue() ? 'Atrasado: o prazo era' : 'Contatar até' }} {{ optional($booking->contact_due_at)->format('d/m/Y H:i') ?? '—' }}
                            </p>
                            <form method="POST" action="{{ route('social-booking.contact', $booking) }}" class="mt-3 space-y-2">
                                @csrf
                                <textarea name="contact_notes" rows="2" maxlength="1000" class="{{ $textarea }}" placeholder="Como foi o contato (opcional)" aria-label="Anotação do contato">{{ old('contact_notes') }}</textarea>
                                <x-secondary-button type="submit" size="sm">Registrar contato</x-secondary-button>
                            </form>
                            <p class="mt-2 text-xs text-ink-3">Contrato ok, aprovação e recusa também contam como contato.</p>
                        @endif
                    </section>
                @endif

                {{-- Contrato --}}
                <section class="{{ $box }}">
                    <h2 class="{{ $h2 }}">Contrato</h2>
                    @if ($booking->contractOk())
                        <p class="text-sm text-ok"><x-icon name="check" class="inline h-4 w-4" /> Ok em {{ $booking->contract_ok_at->format('d/m/Y H:i') }} por {{ optional($booking->contractChecker)->name ?? '—' }}</p>
                        @if ($booking->contract_file)
                            <a class="mt-2 inline-block text-sm font-bold text-grena-ink hover:underline" href="{{ route('social-booking.contract.download', $booking) }}">Baixar anexo</a>
                        @endif
                    @else
                        <p class="text-sm text-ink-2">Feito e assinado fora do Lara. Marque aqui quando estiver ok — aprovar depende disso.</p>
                    @endif
                    @if ($canDecide && $booking->isActive())
                        <form method="POST" action="{{ route('social-booking.contract', $booking) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                            @csrf
                            <input type="file" name="contract_file" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-xs text-ink-2" aria-label="Anexo do contrato (opcional)">
                            <x-secondary-button type="submit" size="sm">{{ $booking->contractOk() ? 'Trocar anexo' : 'Contrato ok' }}</x-secondary-button>
                        </form>
                    @endif
                </section>

                {{-- Decisão --}}
                @if ($canDecide && $booking->stage === 'requested')
                    <section class="{{ $box }}">
                        <h2 class="{{ $h2 }}">Aprovar</h2>
                        <form method="POST" action="{{ route('social-booking.approve', $booking) }}" class="space-y-3">
                            @csrf
                            <div><x-input-label for="discount" value="Desconto (R$)" /><x-text-input type="number" step="0.01" min="0" max="{{ $booking->grossTotal() }}" id="discount" name="discount" class="mt-1" :value="old('discount', 0)" /></div>
                            <div><x-input-label for="discount_reason" value="Motivo do desconto" /><x-text-input id="discount_reason" name="discount_reason" class="mt-1" :value="old('discount_reason')" maxlength="255" /></div>
                            <p class="text-xs text-ink-3">Aprovar libera o pagamento no app com prazo de {{ optional(optional($booking->place)->group)->socialPaymentDays() }} dia(s) (no máximo até a véspera).</p>
                            <x-primary-button size="sm" :disabled="! $booking->contractOk()">Aprovar e liberar pagamento</x-primary-button>
                        </form>
                    </section>
                    <section class="{{ $box }}">
                        <h2 class="{{ $h2 }}">Recusar</h2>
                        <form method="POST" action="{{ route('social-booking.refuse', $booking) }}" class="space-y-3">
                            @csrf
                            <textarea name="refusal_reason" rows="2" required maxlength="1000" class="{{ $textarea }}" placeholder="Motivo (vai no e-mail ao sócio)" aria-label="Motivo da recusa">{{ old('refusal_reason') }}</textarea>
                            <x-danger-button size="sm">Recusar pré-reserva</x-danger-button>
                        </form>
                    </section>
                @endif

                @if ($canDecide && $booking->isActive())
                    <section class="{{ $box }}">
                        <h2 class="{{ $h2 }}">Cancelar</h2>
                        <form method="POST" action="{{ route('social-booking.cancel', $booking) }}" class="space-y-3">
                            @csrf
                            <textarea name="cancel_reason" rows="2" required maxlength="1000" class="{{ $textarea }}" placeholder="Motivo (vai no e-mail ao sócio)" aria-label="Motivo do cancelamento">{{ old('cancel_reason') }}</textarea>
                            @if ($payment && $payment->is_manual)
                                <p class="text-xs text-ink-3">Pago por baixa manual: não há estorno pela Rede. Se houver devolução, ela é feita fora do Lara.</p>
                            @elseif ($payment && $refundable > 0)
                                <div>
                                    <x-input-label for="refund_amount" value="Estornar (R$) — disponível {{ $money($refundable) }}" />
                                    <x-text-input type="number" step="0.01" min="0" max="{{ $refundable }}" id="refund_amount" name="refund_amount" class="mt-1" :value="old('refund_amount', 0)" :disabled="! $canRefund" />
                                    @unless ($canRefund)<p class="mt-1 text-xs text-ink-3">Estornar pede a permissão de estorno de pagamentos.</p>@endunless
                                    <p class="mt-1 text-xs text-ink-3">O valor é decisão da equipe; 0 cancela sem devolver.</p>
                                </div>
                            @endif
                            <x-danger-button size="sm">Cancelar reserva</x-danger-button>
                        </form>
                    </section>
                @endif
            </aside>
        </div>
    </x-page>

    <script>
        (function () {
            const add = document.getElementById('add-guest');
            const list = document.getElementById('guests');
            if (!add || !list) return;
            let index = {{ count($guestRows) }};
            add.addEventListener('click', () => {
                list.insertAdjacentHTML('beforeend', document.getElementById('guest-template').innerHTML.replaceAll('__I__', index++));
            });
            list.addEventListener('click', e => {
                if (e.target.closest('[data-remove-guest]')) e.target.closest('[data-guest-row]').remove();
            });
        })();
    </script>
</x-app-layout>
