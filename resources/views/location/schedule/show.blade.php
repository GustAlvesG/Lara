<x-app-layout>


    <x-slot name="css">
       
    </x-slot>

    <div class="max-w-7xl mx-auto pt-4">
        
        <!-- HEADER DE NAVEGAÇÃO -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('schedule.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-ink leading-tight">Gestão de Reserva</h1>
                    <p class="text-ink-2 font-medium">Visualize detalhes ou altere o status deste agendamento.</p>
                </div>
            </div>

            @can('reservas.pagamentos')
                @if($data['schedule']->schedulePayment)
                    <a href="{{ route('payment.show', $data['schedule']->schedulePayment->id) }}"
                       class="flex items-center gap-2 px-5 py-3 bg-surface rounded-xl shadow-card text-grena-ink hover:text-white hover:bg-grena-hover border border-line hover:border-grena transition font-black text-xs uppercase tracking-widest">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a4 4 0 00-8 0v2M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        Ver Pagamento Vinculado
                    </a>
                @endif
            @endcan
        </div>

        @php
            // Mapeamento baseado no novo JSON
            $schedule = $data['schedule'];
            $member = $schedule->member;
            $place = $schedule->place;
            $otherSchedules = $data['other_schedules'] ?? [];
            
            // Dados de Auditoria
            $createdByUser = optional($schedule->creator)->name;
            $updatedByUser = optional($schedule->editor)->name;
            $cancelledByUser = optional($schedule->canceller)->name;
            
            // Lógica de Status
            $statusId = $schedule->status_id;
            $isPending = $statusId == 3;
            $isCanceled = $statusId == 0;
            
            $statusConfig = match((int)$statusId) {
                1 => ['bg' => 'bg-ok', 'text' => 'Reserva Confirmada'],
                3 => ['bg' => 'bg-warn', 'text' => 'Pagamento Pendente'],
                0 => ['bg' => 'bg-danger', 'text' => 'Reserva Cancelada'],
                10 => ['bg' => 'bg-ink-2', 'text' => 'Antigo / Expirado'],
                default => ['bg' => 'bg-ink-3', 'text' => 'Status Desconhecido'],
            };

            $headerBg = $statusConfig['bg'];
            $statusText = $statusConfig['text'];

            // Identifica se foi criado via site (User ID nulo ou flag específica)
            $isViaSite = empty($schedule->created_by_user);
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- COLUNA DA ESQUERDA: DETALHES DA RESERVA -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-surface rounded-3xl shadow-pop border border-line overflow-hidden sticky top-8">
                    
                    <!-- Cabeçalho de Status -->
                    <div class="{{ $headerBg }} p-8 text-white dark:text-canvas text-center relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="relative z-10">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center text-3xl font-black mx-auto mb-4 shadow-pop border border-white/30 uppercase">
                                {{ strtoupper(substr($member->name ?? '??', 0, 2)) }}
                            </div>
                            <h2 class="text-2xl font-black uppercase tracking-tight">{{ $member->name ?? 'Sócio não identificado' }}</h2>
                            <span class="inline-block mt-2 px-4 py-1 bg-black/20 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20">
                                {{ $statusText }}
                            </span>
                        </div>
                    </div>

                    <div class="p-8 space-y-6">
                        <!-- Grade de Info Rápida -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-subtle rounded-2xl border border-line">
                                <p class="text-[10px] font-black text-ink-3 uppercase mb-1">Quadra/Local</p>
                                <p class="text-sm font-bold text-ink">{{ $place->name ?? 'Local Indefinido' }}</p>
                            </div>
                            <div class="p-4 bg-subtle rounded-2xl border border-line">
                                <p class="text-[10px] font-black text-ink-3 uppercase mb-1">Data</p>
                                <p class="text-sm font-bold text-ink">
                                    {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('d/m/Y') }}
                                </p>
                            </div>
                        </div>

                        <!-- Detalhes do Sócio -->
                        <div class="space-y-4 pb-6 border-b border-line">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-grena-tint text-grena-ink rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-ink-3 uppercase">Matrícula / Título</p>
                                    <p class="text-sm font-bold text-ink">{{ $member->title ?? '---' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-grena-tint text-grena-ink rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-ink-3 uppercase">E-mail de Contato</p>
                                    <p class="text-sm font-bold text-ink truncate">{{ $member->email ?? 'Não informado' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SEÇÃO DE AUDITORIA -->
                        <div class="pt-2 space-y-4">
                            <h3 class="text-[10px] font-black text-ink-3 uppercase tracking-widest">Histórico e Auditoria</h3>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <!-- Criação -->
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 w-2 h-2 rounded-full bg-ok shadow-card"></div>
                                    <div class="flex-grow">
                                        <p class="text-[10px] font-bold text-ink-2 uppercase leading-none mb-1">Criado por</p>
                                        <p class="text-xs font-extrabold text-ink">{{ $createdByUser ?? 'Via Site' }}</p>
                                        <p class="text-[10px] text-ink-3 mt-0.5">Em: {{ \Carbon\Carbon::parse($schedule->created_at)->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
           
                                @if($schedule->updated_at && $schedule->created_at != $schedule->updated_at)
                                <!-- Atualização -->
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 w-2 h-2 rounded-full {{ $updatedByUser ? 'bg-grena' : 'bg-line' }}"></div>
                                    <div class="flex-grow">
                                        <p class="text-[10px] font-bold text-ink-2 uppercase leading-none mb-1">Última Atualização</p>
                                        <p class="text-xs font-extrabold text-ink">
                                            {{ $updatedByUser ?? 'Via Site' }}
                                        </p>
                                        <p class="text-[10px] text-ink-3 mt-0.5">
                                            
                                                Em: {{ \Carbon\Carbon::parse($schedule->updated_at)->format('d/m/Y H:i') }}
                                            
                                            
                                        </p>
                                    </div>
                                </div>
                                @endif

                                @if($isCanceled)
                                <!-- Cancelamento -->
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 w-2 h-2 rounded-full bg-danger shadow-card"></div>
                                    <div class="flex-grow">
                                        <p class="text-[10px] font-bold text-ink-2 uppercase leading-none mb-1">Cancelado por</p>
                                        <p class="text-xs font-extrabold text-ink">
                                            {{ $cancelledByUser ?? $updatedByUser ?? 'Via Site' }}
                                        </p>
                                        @if($schedule->cancelled_at)
                                        <p class="text-[10px] text-ink-3 mt-0.5">Em: {{ \Carbon\Carbon::parse($schedule->cancelled_at)->format('d/m/Y H:i') }}</p>
                                        @endif
                                        @if($schedule->cancel_reason)
                                        <p class="text-[10px] text-ink-2 mt-1 italic">"{{ $schedule->cancel_reason }}"</p>
                                        @endif
                                    </div>
                                </div>
                                @endif

                            </div>
                        </div>

                        <!-- Contador de Tempo (Somente para Status Pendente) -->
                        @if($isPending)
                            @php
                                $seconds = \Carbon\Carbon::parse($schedule->created_at)->diffInSeconds(now());
                                $formatted = gmdate('i:s', $seconds);
                            @endphp
                            <div class="flex items-center gap-4 p-4 bg-warn-soft rounded-2xl border border-warn/40 mt-4">
                                <div class="p-3 bg-warn text-white dark:text-canvas rounded-xl shadow-card animate-pulse-fast">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-warn uppercase">Tempo de Espera</p>
                                    <p class="text-sm font-black text-warn">Aguardando pagamento há {{ $formatted }} minutos</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- COLUNA DA DIREITA: FORMULÁRIO DE AÇÃO -->
            <div class="lg:col-span-7">
                <form action="{{ route('schedule.update') }}" method="POST" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Secção: Seleção Múltipla de Agendamentos -->
                    <div class="bg-surface rounded-3xl shadow-pop border border-line p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-grena rounded-lg text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </div>
                            <h3 class="text-xl font-extrabold text-ink">Agendamentos Vinculados</h3>
                        </div>

                        @php
                            $countOthers = collect($otherSchedules)->where('id', '!=', $schedule->id)->count();
                        @endphp

                        @if($countOthers > 0)
                        <div class="p-4 mb-6 rounded-2xl bg-grena-tint border border-grena/40 text-grena-ink text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            O membro possui <span class="font-black">{{ $countOthers }} outro(s) agendamento(s)</span> neste dia. Selecione-os para aplicar a alteração em massa.
                        </div>
                        @else
                        <div class="p-4 mb-6 rounded-2xl bg-subtle border border-line text-ink-2 text-xs italic text-center">
                            Nenhum outro agendamento encontrado para este sócio nesta data.
                        </div>
                        @endif

                        <div class="space-y-3">
                            <!-- Card do Agendamento Atual (Travado) -->
                            <label class="relative flex items-center p-4 border-2 border-grena bg-grena-tint rounded-2xl cursor-default shadow-card">
                                <input type="checkbox" name="selected_reservations[]" value="{{ $schedule->id }}" checked onclick="return false;" class="w-5 h-5 text-grena-ink border-line-strong rounded focus:ring-grena-tint">
                                <div class="ml-4">
                                    <p class="text-[10px] font-black text-grena-ink uppercase tracking-widest mb-1">Reserva Principal</p>
                                    <p class="text-lg font-black text-grena-ink leading-none">
                                        #{{ $schedule->id }} — {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($schedule->end_schedule)->format('H:i') }}
                                    </p>
                                    <p class="text-xs font-bold text-grena-ink mt-1">{{ $place->name ?? 'Local Principal' }}</p>
                                </div>
                            </label>
                            <!-- Outros agendamentos -->
                            @foreach($otherSchedules as $other)
                                @if($other->id !== $schedule->id)
                                    @php
                                        $otherStatus = match((int)$other->status_id) {
                                            1 => ['label' => 'Confirmado', 'class' => 'bg-ok-soft text-ok'],
                                            3 => ['label' => 'Pendente', 'class' => 'bg-warn-soft text-warn'],
                                            0 => ['label' => 'Cancelado', 'class' => 'bg-danger-soft text-danger'],
                                            10 => ['label' => 'Antigo', 'class' => 'bg-subtle text-ink-2'],
                                            default => ['label' => '?', 'class' => 'bg-subtle text-ink-3'],
                                        };
                                        $isCanceledOther = $other->status_id == 0;
                                    @endphp

                                    @if($isCanceledOther || $isCanceled)
                                    <div class="relative flex items-center p-4 border-2 border-line rounded-2xl bg-subtle opacity-60 cursor-not-allowed">
                                        <div class="w-5 h-5 border-line-strong rounded bg-line"></div>
                                        <div class="ml-4">
                                            <p class="text-[10px] font-bold text-ink-3 uppercase tracking-widest mb-1">Mesmo Sócio / Mesma Data</p>
                                            <p class="text-lg font-black text-ink-2 leading-none">
                                                #{{ $other->id }} — {{ \Carbon\Carbon::parse($other->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($other->end_schedule)->format('H:i') }}
                                            </p>
                                            <p class="text-[10px] font-bold text-danger mt-1 uppercase">Esse agendamento não pode ser alterado</p>
                                        </div>
                                        <div class="ml-auto text-right">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase {{ $otherStatus['class'] }}">
                                                {{ $otherStatus['label'] }}
                                            </span>
                                        </div>
                                    </div>
                                    @else
                                    <label class="relative flex items-center p-4 border-2 border-line rounded-2xl cursor-pointer transition group hover:bg-subtle has-[:checked]:border-grena has-[:checked]:bg-grena-tint">
                                        <input type="checkbox" name="selected_reservations[]" value="{{ $other->id }}" class="w-5 h-5 text-grena-ink border-line-strong rounded focus:ring-grena-tint">
                                        <div class="ml-4">
                                            <p class="text-[10px] font-bold text-ink-3 uppercase tracking-widest mb-1">Mesmo Sócio / Mesma Data</p>
                                            <p class="text-lg font-black text-ink leading-none group-has-[:checked]:text-grena-ink">
                                                #{{ $other->id }} — {{ \Carbon\Carbon::parse($other->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($other->end_schedule)->format('H:i') }}
                                            </p>
                                            <p class="text-xs font-bold text-ink-2 group-has-[:checked]:text-grena-ink mt-1">{{ $other->place->name ?? 'Outro Local' }}</p>
                                        </div>
                                        <div class="ml-auto text-right">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase {{ $otherStatus['class'] }}">
                                                {{ $otherStatus['label'] }}
                                            </span>
                                        </div>
                                    </label>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </div>
                    
                    @if (($isPending || $isCanceled))
                        @if($isPending)
                        <div class="p-4 mb-6 rounded-2xl bg-warn-soft border border-warn/40 text-warn text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Este agendamento está com o pagamento <span class="font-black">PENDENTE</span>. Por favor aguarde.
                        </div>
                        @else
                        <div class="p-4 mb-6 rounded-2xl bg-danger-soft border border-danger/40 text-danger text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Este agendamento já está <span class="font-black">CANCELADO</span>. Não é possível realizar alterações.
                        </div>
                        @endif
                    @endif

                    @can('reservas.agendamentos')
                        <!-- Secção: Operação a Realizar -->
                        <div class="bg-surface rounded-3xl shadow-pop border border-line p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="p-2 bg-warn rounded-lg text-white dark:text-canvas">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </div>
                                <h3 class="text-xl font-extrabold text-ink">Operação Desejada</h3>
                            </div>
                            @if($schedule->status_id == 0)
                                <div class="p-4 mb-6 rounded-2xl bg-danger-soft border border-danger/40 text-danger text-sm font-medium leading-relaxed">
                                    <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Este agendamento já está <span class="font-black">CANCELADO</span>. Não é possível realizar alterações adicionais.
                                </div>
                            @else
                            <div class="space-y-4">
                                <div>
                                    <label for="action_status" class="block text-xs font-black text-ink-3 uppercase mb-2 tracking-widest ml-1">Selecione o Novo Estado</label>
                                    <select id="action_status" name="action_status" required onchange="handleActionChange(this)"
                                            class="block w-full py-4 px-4 bg-subtle border border-line rounded-2xl focus:ring-4 focus:ring-grena-tint focus:border-grena outline-none transition font-extrabold text-ink">
                                        <option value="">O que deseja fazer?</option>
                                        {{-- <option value="1">✅ Confirmar Agendamento(s)</option> --}}
                                        <option value="0">❌ Cancelar Agendamento(s)</option>
                                    </select>
                                </div>

                                <!-- OPÇÕES DINÂMICAS DE CANCELAMENTO -->
                                <div id="cancel-options" class="hidden animate-fadeIn space-y-3 bg-danger-soft p-6 rounded-2xl border border-danger/40">
                                    <p class="text-[10px] font-black text-danger uppercase tracking-widest mb-2">Configurações de Cancelamento</p>

                                    <div>
                                        <label for="cancel_reason" class="block text-xs font-black text-danger uppercase mb-2 tracking-widest">Motivo do Cancelamento</label>
                                        <textarea id="cancel_reason" name="cancel_reason" rows="3" maxlength="1000"
                                            class="block w-full py-3 px-4 bg-surface border border-danger/40 rounded-2xl focus:ring-4 focus:ring-danger-soft focus:border-grena outline-none transition font-medium text-ink"
                                            placeholder="Descreva o motivo do cancelamento">{{ old('cancel_reason') }}</textarea>
                                        @error('cancel_reason')
                                            <p class="text-xs font-bold text-danger mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" name="confirm_cancel" required class="w-5 h-5 text-danger border-danger/40 rounded focus:ring-grena-tint transition">
                                        <span class="text-sm font-bold text-danger group-hover:text-grena-ink transition">Confirmo que desejo cancelar permanentemente</span>
                                    </label>

                                    @if($isViaSite && !$isPending)
                                    <label class="flex items-center gap-3 cursor-pointer group">
                                        <input type="checkbox" name="refund_payment" value="1" class="w-5 h-5 text-danger border-danger/40 rounded focus:ring-grena-tint transition">
                                        <span class="text-sm font-bold text-danger group-hover:text-grena-ink transition">Solicitar estorno do valor pago (Agendamento via Site)</span>
                                    </label>
                                    @endif
                                </div>

                                <div class="pt-4 border-t border-line">
                                    <button type="submit" class="w-full py-4 bg-ink text-canvas rounded-2xl font-black uppercase tracking-widest shadow-pop hover:bg-grena-hover transition transform hover:scale-[1.01] active:scale-95">
                                        Salvar Alterações
                                    </button>
                                </div>
                            </div>
                            @endif
                        </div>
                    @endcan
                </form>
            </div>

        </div>
    </div>

    <script>
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
