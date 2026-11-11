{{--
    Gestão de uma reserva (refeita em 11/11/2026, identidade de Reservas): à
    esquerda o "ingresso" da quadra (local, dia, horário, sócio, carimbo do
    estado, auditoria); à direita os agendamentos do mesmo sócio no dia, o
    cancelamento e a baixa manual. Campos, rotas e regras são os de antes.
--}}
@php
    $schedule = $data['schedule'];
    $member = $schedule->member;
    $place = $schedule->place;
    $otherSchedules = $data['other_schedules'] ?? [];

    $start = \Carbon\Carbon::parse($schedule->start_schedule);
    $end = \Carbon\Carbon::parse($schedule->end_schedule);

    // Auditoria
    $createdByUser = optional($schedule->creator)->name;
    $updatedByUser = optional($schedule->editor)->name;
    $cancelledByUser = optional($schedule->canceller)->name;

    $statusId = $schedule->status_id;
    $isPending = $statusId == 3;
    $isCanceled = $statusId == 0;

    // Carimbo do ingresso: texto e cor clara (fica sobre o verde da quadra).
    [$stampText, $stampColor] = match ((int) $statusId) {
        1 => ['Reserva confirmada', 'rgb(134 239 172)'],
        3 => ['Pagamento pendente', 'rgb(var(--res-clay))'],
        0 => ['Reserva cancelada', 'rgb(252 165 165)'],
        10 => ['Antigo / expirado', 'rgb(255 255 255 / .7)'],
        default => ['Status desconhecido', 'rgb(255 255 255 / .7)'],
    };

    // Criado pelo site: sem usuário do Lara.
    $isViaSite = empty($schedule->created_by_user);

    $panel = 'rounded-card bg-surface p-5 shadow-card sm:p-6';
    $panelTitle = 'font-display text-lg font-semibold tracking-tight text-ink';
    $eyebrow = 'text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Gestão da reserva" :back="route('schedule.index')">
            Veja os detalhes ou altere a situação deste agendamento.
            @can('reservas.pagamentos')
                @if ($schedule->schedulePayment)
                    <x-slot:actions>
                        <x-secondary-button-a href="{{ route('payment.show', $schedule->schedulePayment->id) }}">
                            <x-icon name="tag" /> Ver pagamento vinculado
                        </x-secondary-button-a>
                    </x-slot:actions>
                @endif
            @endcan
        </x-page-title>

        @include('partials.alerts')

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">

            {{-- ============================ Ingresso ============================ --}}
            <div class="flex flex-col gap-6 lg:sticky lg:top-[var(--split-top)] lg:col-span-5">
                <article class="res-court res-ticket overflow-hidden rounded-[28px] shadow-card" style="--cut: 64%" aria-label="Reserva #{{ $schedule->id }}">
                    <div class="auth-enter flex flex-col gap-6 p-6 sm:p-8">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/65">{{ optional($place?->group)->name ?? 'Reserva' }} · #{{ $schedule->id }}</p>
                                <h2 class="mt-2 font-display text-[26px] font-semibold leading-tight tracking-tight sm:text-[30px]">{{ $place->name ?? 'Local indefinido' }}</h2>
                            </div>
                            <span class="res-stamp shrink-0" style="color: {{ $stampColor }}">{{ $stampText }}</span>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/65">Dia</p>
                                <p class="mt-1 font-display text-xl font-semibold first-letter:uppercase">{{ $start->translatedFormat('D, d/m') }}</p>
                                <p class="font-mono text-xs text-white/60">{{ $start->format('d/m/Y') }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-white/65">Horário</p>
                                <p class="mt-1 font-mono text-[26px] font-semibold leading-none">{{ $start->format('H:i') }}</p>
                                <p class="font-mono text-xs text-white/60">até {{ $end->format('H:i') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="res-perforation" aria-hidden="true"></div>

                    <div class="flex items-center gap-4 p-6 sm:px-8">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/15 font-display text-base font-semibold uppercase ring-1 ring-white/25">
                            {{ strtoupper(mb_substr($member->name ?? '??', 0, 2)) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-bold">{{ $member->name ?? 'Sócio não identificado' }}</p>
                            <p class="truncate text-sm text-white/70">
                                Título <span class="font-mono">{{ $member->title ?? '—' }}</span>
                                @if ($member?->email) · {{ $member->email }} @endif
                            </p>
                        </div>
                        <span class="ml-auto font-mono text-lg font-semibold">R$ {{ number_format((float) ($schedule->price ?? 0), 2, ',', '.') }}</span>
                    </div>
                </article>

                {{-- Tempo de espera do pagamento (só pendente). --}}
                @if ($isPending)
                    @php $formatted = gmdate('i:s', \Carbon\Carbon::parse($schedule->created_at)->diffInSeconds(now())); @endphp
                    <div class="flex items-center gap-4 rounded-card bg-warn-soft p-4">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-warn text-white dark:text-canvas">
                            <x-icon name="clock" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-warn">Tempo de espera</p>
                            <p class="text-sm font-bold text-warn">Aguardando pagamento há <span class="font-mono">{{ $formatted }}</span> minutos</p>
                        </div>
                    </div>
                @endif

                {{-- Auditoria --}}
                <section class="{{ $panel }}">
                    <h3 class="{{ $eyebrow }}">Histórico e auditoria</h3>
                    <ol class="mt-4 flex flex-col gap-4 border-l-2 border-line pl-5">
                        <li class="relative">
                            <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full bg-ok ring-4 ring-surface" aria-hidden="true"></span>
                            <p class="text-xs font-bold text-ink-3">Criado por</p>
                            <p class="font-semibold text-ink">{{ $createdByUser ?? 'Via Site' }}</p>
                            <p class="font-mono text-xs text-ink-3">{{ \Carbon\Carbon::parse($schedule->created_at)->format('d/m/Y H:i') }}</p>
                        </li>
                        @if ($schedule->updated_at && $schedule->created_at != $schedule->updated_at)
                            <li class="relative">
                                <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full ring-4 ring-surface {{ $updatedByUser ? 'bg-grena' : 'bg-line-strong' }}" aria-hidden="true"></span>
                                <p class="text-xs font-bold text-ink-3">Última atualização</p>
                                <p class="font-semibold text-ink">{{ $updatedByUser ?? 'Via Site' }}</p>
                                <p class="font-mono text-xs text-ink-3">{{ \Carbon\Carbon::parse($schedule->updated_at)->format('d/m/Y H:i') }}</p>
                            </li>
                        @endif
                        @if ($isCanceled)
                            <li class="relative">
                                <span class="absolute -left-[27px] top-1 h-3 w-3 rounded-full bg-danger ring-4 ring-surface" aria-hidden="true"></span>
                                <p class="text-xs font-bold text-ink-3">Cancelado por</p>
                                <p class="font-semibold text-ink">{{ $cancelledByUser ?? $updatedByUser ?? 'Via Site' }}</p>
                                @if ($schedule->cancelled_at)
                                    <p class="font-mono text-xs text-ink-3">{{ \Carbon\Carbon::parse($schedule->cancelled_at)->format('d/m/Y H:i') }}</p>
                                @endif
                                @if ($schedule->cancel_reason)
                                    <p class="mt-1 text-sm italic text-ink-2">"{{ $schedule->cancel_reason }}"</p>
                                @endif
                            </li>
                        @endif
                    </ol>
                </section>
            </div>

            {{-- ============================= Ações ============================== --}}
            <div class="flex flex-col gap-6 lg:col-span-7">
                @if ($schedule->socialBooking)
                    {{-- Reserva de espaço social: estágio, convidados na portaria e
                         estorno têm regra própria; mudar só o status daqui os deixaria
                         para trás (o serviço também recusa). --}}
                    <section class="{{ $panel }}">
                        <p class="{{ $eyebrow }}">Reserva de espaço social</p>
                        <p class="mt-2 text-ink-2">Este dia inteiro pertence à reserva social #{{ $schedule->socialBooking->id }} ({{ $schedule->socialBooking->stageLabel() }}). Aprovar, cancelar e cuidar dos convidados é pela tela dela.</p>
                        @can(\App\Authorization\Permissions::RESERVAS_SOCIAIS)
                            <x-primary-button-a class="mt-4" href="{{ route('social-booking.show', $schedule->socialBooking) }}">Abrir reserva social</x-primary-button-a>
                        @endcan
                    </section>
                @else
                    <form action="{{ route('schedule.update') }}" method="POST" class="flex flex-col gap-6">
                        @csrf
                        @method('PUT')

                        {{-- Agendamentos do mesmo sócio no mesmo dia. --}}
                        <section class="{{ $panel }}">
                            <h3 class="{{ $panelTitle }}">Agendamentos vinculados</h3>

                            @php $countOthers = collect($otherSchedules)->where('id', '!=', $schedule->id)->count(); @endphp
                            <p class="mt-1 text-sm text-ink-2">
                                @if ($countOthers > 0)
                                    O sócio tem <b class="text-ink">{{ $countOthers }} outro(s) agendamento(s)</b> neste dia. Marque-os para aplicar a mesma alteração.
                                @else
                                    Nenhum outro agendamento deste sócio nesta data.
                                @endif
                            </p>

                            <div class="mt-4 flex flex-col gap-2.5">
                                {{-- O atual vai sempre (travado). --}}
                                <label class="flex items-center gap-4 rounded-2xl border-2 border-[rgb(var(--res-court))] bg-[rgb(var(--res-court)/.08)] p-4">
                                    <input type="checkbox" name="selected_reservations[]" value="{{ $schedule->id }}" checked onclick="return false;" class="h-5 w-5 rounded border-line-strong text-[rgb(var(--res-court))] focus:ring-grena-tint">
                                    <span class="min-w-0">
                                        <span class="block text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Reserva principal</span>
                                        <span class="block font-mono text-base font-semibold text-ink">#{{ $schedule->id }} · {{ $start->format('H:i') }} às {{ $end->format('H:i') }}</span>
                                        <span class="block text-sm text-ink-2">{{ $place->name ?? 'Local principal' }}</span>
                                    </span>
                                </label>

                                @foreach ($otherSchedules as $other)
                                    @if ($other->id !== $schedule->id)
                                        @php
                                            [$otherKind, $otherLabel] = match ((int) $other->status_id) {
                                                1 => ['ok', 'Confirmado'],
                                                3 => ['warn', 'Pendente'],
                                                0 => ['danger', 'Cancelado'],
                                                10 => ['off', 'Antigo'],
                                                default => ['off', '?'],
                                            };
                                            $otherRange = '#' . $other->id . ' · ' . \Carbon\Carbon::parse($other->start_schedule)->format('H:i') . ' às ' . \Carbon\Carbon::parse($other->end_schedule)->format('H:i');
                                        @endphp

                                        @if ($other->status_id == 0 || $isCanceled)
                                            <div class="flex items-center gap-4 rounded-2xl border border-line bg-subtle p-4 opacity-70">
                                                <span class="h-5 w-5 shrink-0 rounded bg-line" aria-hidden="true"></span>
                                                <span class="min-w-0">
                                                    <span class="block font-mono text-base font-semibold text-ink-2">{{ $otherRange }}</span>
                                                    <span class="block text-xs font-bold text-danger">Esse agendamento não pode ser alterado</span>
                                                </span>
                                                <x-pill :kind="$otherKind" class="ml-auto">{{ $otherLabel }}</x-pill>
                                            </div>
                                        @else
                                            <label class="flex cursor-pointer items-center gap-4 rounded-2xl border border-line p-4 transition hover:bg-subtle has-[:checked]:border-[rgb(var(--res-court))] has-[:checked]:bg-[rgb(var(--res-court)/.08)]">
                                                <input type="checkbox" name="selected_reservations[]" value="{{ $other->id }}" class="h-5 w-5 rounded border-line-strong text-[rgb(var(--res-court))] focus:ring-grena-tint">
                                                <span class="min-w-0">
                                                    <span class="block text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Mesmo sócio, mesmo dia</span>
                                                    <span class="block font-mono text-base font-semibold text-ink">{{ $otherRange }}</span>
                                                    <span class="block text-sm text-ink-2">{{ $other->place->name ?? 'Outro local' }}</span>
                                                </span>
                                                <x-pill :kind="$otherKind" class="ml-auto">{{ $otherLabel }}</x-pill>
                                            </label>
                                        @endif
                                    @endif
                                @endforeach
                            </div>
                        </section>

                        @if ($isPending)
                            <div class="rounded-card bg-warn-soft p-4 text-sm font-semibold text-warn">
                                Este agendamento está com o pagamento <b>PENDENTE</b>. Por favor, aguarde.
                            </div>
                        @elseif ($isCanceled)
                            <div class="rounded-card bg-danger-soft p-4 text-sm font-semibold text-danger">
                                Este agendamento já está <b>CANCELADO</b>. Não é possível fazer alterações.
                            </div>
                        @endif

                        @can('reservas.agendamentos')
                            @unless ($isCanceled)
                                <section class="{{ $panel }} flex flex-col gap-4">
                                    <h3 class="{{ $panelTitle }}">Alterar a reserva</h3>

                                    <div>
                                        <label for="action_status" class="mb-1.5 block text-xs font-bold text-ink-2">O que deseja fazer?</label>
                                        <x-select-input id="action_status" name="action_status" required onchange="handleActionChange(this)">
                                            <option value="">Escolha</option>
                                            {{-- <option value="1">Confirmar agendamento(s)</option> --}}
                                            <option value="0">Cancelar agendamento(s)</option>
                                        </x-select-input>
                                    </div>

                                    {{-- Aparece ao escolher "Cancelar". --}}
                                    <div id="cancel-options" class="hidden flex-col gap-3 rounded-2xl bg-danger-soft p-5 [&:not(.hidden)]:flex">
                                        <div>
                                            <label for="cancel_reason" class="mb-1.5 block text-xs font-bold text-danger">Motivo do cancelamento</label>
                                            <textarea id="cancel_reason" name="cancel_reason" rows="3" maxlength="1000"
                                                      class="block w-full rounded-2xl border-danger/40 bg-surface px-4 py-3 text-ink focus:border-grena focus:ring-4 focus:ring-danger-soft"
                                                      placeholder="Descreva o motivo do cancelamento">{{ old('cancel_reason') }}</textarea>
                                            @error('cancel_reason')
                                                <p class="mt-1 text-xs font-bold text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <label class="flex cursor-pointer items-center gap-3 text-sm font-bold text-danger">
                                            <input type="checkbox" name="confirm_cancel" required class="h-5 w-5 rounded border-danger/40 text-danger focus:ring-grena-tint">
                                            Confirmo que desejo cancelar permanentemente
                                        </label>

                                        {{-- Só pagamento da Rede se estorna por aqui; baixa manual não. --}}
                                        @if ($isViaSite && ! $isPending && optional($schedule->schedulePayment)->isOnline())
                                            <label class="flex cursor-pointer items-center gap-3 text-sm font-bold text-danger">
                                                <input type="checkbox" name="refund_payment" value="1" class="h-5 w-5 rounded border-danger/40 text-danger focus:ring-grena-tint">
                                                Solicitar estorno do valor pago (agendamento via site)
                                            </label>
                                        @endif
                                    </div>

                                    <div class="border-t border-line pt-4">
                                        <x-primary-button class="w-full justify-center"><x-icon name="check" /> Salvar alterações</x-primary-button>
                                    </div>
                                </section>
                            @endunless
                        @endcan
                    </form>

                    {{-- Baixa manual: pagamento recebido fora da plataforma. Entra o
                         agendamento atual e, se marcados, os do mesmo sócio no dia
                         ainda sem pagamento. --}}
                    @php
                        $manualEligible = collect($otherSchedules)->filter(fn ($s) => ! $s->schedule_payment_id
                            && in_array((int) $s->status_id, [1, 3], true)
                            && ! $s->socialBooking);
                    @endphp
                    @can(\App\Authorization\Permissions::RESERVAS_PAGAMENTOS_BAIXA_MANUAL)
                        @if ($manualEligible->contains('id', $schedule->id))
                            <form method="POST" action="{{ route('payment.manual') }}" class="{{ $panel }} flex flex-col gap-4"
                                  onsubmit="return confirm('Confirma que este pagamento foi recebido fora da plataforma?');">
                                @csrf
                                <div>
                                    <h3 class="{{ $panelTitle }}">Baixa manual de pagamento</h3>
                                    <p class="mt-1 text-sm text-ink-2">Pago fora da plataforma (dinheiro, Pix direto, maquininha). {{ $isPending ? 'O agendamento pendente passa a confirmado.' : '' }}</p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    @foreach ($manualEligible as $eligible)
                                        <label class="flex flex-wrap items-center gap-3 rounded-2xl border border-line p-3 text-sm">
                                            <input type="checkbox" name="schedule_ids[]" value="{{ $eligible->id }}" data-price="{{ (float) $eligible->price }}"
                                                   class="manual-schedule h-5 w-5 rounded border-line-strong text-grena"
                                                   @checked($eligible->id === $schedule->id) @if ($eligible->id === $schedule->id) onclick="return false;" @endif>
                                            <span class="font-mono font-semibold text-ink">#{{ $eligible->id }} · {{ \Carbon\Carbon::parse($eligible->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($eligible->end_schedule)->format('H:i') }}</span>
                                            <span class="text-ink-2">{{ optional($eligible->place)->name }}</span>
                                            <span class="ml-auto font-mono text-ink">R$ {{ number_format((float) $eligible->price, 2, ',', '.') }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div>
                                    <x-input-label for="manual_amount" value="Valor recebido (R$)" />
                                    <x-text-input type="number" step="0.01" min="0" id="manual_amount" name="amount" class="mt-1 sm:w-48" :value="old('amount', number_format((float) $schedule->price, 2, '.', ''))" />
                                    <p class="mt-1 text-xs text-ink-3">Começa pela soma dos agendamentos marcados.</p>
                                </div>

                                @include('payments.partials.manual-fields')

                                <x-green-button class="w-full justify-center"><x-icon name="check" /> Marcar como pago</x-green-button>
                            </form>
                        @endif
                    @endcan
                @endif
            </div>
        </div>
    </x-page>

    <script>
        // Baixa manual: o valor acompanha a soma dos agendamentos marcados.
        document.querySelectorAll('.manual-schedule').forEach(box => box.addEventListener('change', () => {
            const total = [...document.querySelectorAll('.manual-schedule:checked')].reduce((sum, b) => sum + parseFloat(b.dataset.price || 0), 0);
            document.getElementById('manual_amount').value = total.toFixed(2);
        }));

        function handleActionChange(select) {
            const cancelOptions = document.getElementById('cancel-options');
            const confirmCheckbox = document.querySelector('input[name="confirm_cancel"]');
            const cancelReason = document.getElementById('cancel_reason');

            if (select.value === '0') {
                cancelOptions.classList.remove('hidden');
                confirmCheckbox.required = true;
                cancelReason.required = true;
            } else {
                cancelOptions.classList.add('hidden');
                confirmCheckbox.required = false;
                cancelReason.required = false;
            }
        }
    </script>
</x-app-layout>
