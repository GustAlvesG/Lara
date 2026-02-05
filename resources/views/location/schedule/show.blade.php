<x-app-layout>


    <x-slot name="css">
       
    </x-slot>

    <div class="max-w-7xl mx-auto pt-4">
        
        <!-- HEADER DE NAVEGAÇÃO -->
        <div class="mb-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('schedule.index') }}" class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Gestão de Reserva</h1>
                    <p class="text-gray-500 font-medium">Visualize detalhes ou altere o status deste agendamento.</p>
                </div>
            </div>
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
            
            // Lógica de Status
            $statusId = $schedule->status_id;
            $isPending = $statusId == 3;
            $isCanceled = $statusId == 0;
            
            $statusConfig = match((int)$statusId) {
                1 => ['bg' => 'bg-green-600', 'text' => 'Reserva Confirmada'],
                3 => ['bg' => 'bg-amber-500', 'text' => 'Pagamento Pendente'],
                0 => ['bg' => 'bg-red-600', 'text' => 'Reserva Cancelada'],
                10 => ['bg' => 'bg-gray-600', 'text' => 'Antigo / Expirado'],
                default => ['bg' => 'bg-gray-400', 'text' => 'Status Desconhecido'],
            };

            $headerBg = $statusConfig['bg'];
            $statusText = $statusConfig['text'];

            // Identifica se foi criado via site (User ID nulo ou flag específica)
            $isViaSite = empty($schedule->created_by_user);
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- COLUNA DA ESQUERDA: DETALHES DA RESERVA -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden sticky top-8">
                    
                    <!-- Cabeçalho de Status -->
                    <div class="{{ $headerBg }} p-8 text-white text-center relative overflow-hidden">
                        <div class="absolute top-0 right-0 -mr-8 -mt-8 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="relative z-10">
                            <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center text-3xl font-black mx-auto mb-4 shadow-xl border border-white/30 uppercase">
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
                            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Quadra/Local</p>
                                <p class="text-sm font-bold text-gray-800">{{ $place->name ?? 'Local Indefinido' }}</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-2xl border border-gray-100">
                                <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Data</p>
                                <p class="text-sm font-bold text-gray-800">
                                    {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('d/m/Y') }}
                                </p>
                            </div>
                        </div>

                        <!-- Detalhes do Sócio -->
                        <div class="space-y-4 pb-6 border-b border-gray-100">
                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-indigo-50 text-indigo-600 rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase">Matrícula / Título</p>
                                    <p class="text-sm font-bold text-gray-800">{{ $member->title ?? '---' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <div class="p-3 bg-indigo-50 text-indigo-600 rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-gray-400 uppercase">E-mail de Contato</p>
                                    <p class="text-sm font-bold text-gray-800 truncate">{{ $member->email ?? 'Não informado' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SEÇÃO DE AUDITORIA -->
                        <div class="pt-2 space-y-4">
                            <h3 class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Histórico e Auditoria</h3>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <!-- Criação -->
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 w-2 h-2 rounded-full bg-green-500 shadow-sm shadow-green-200"></div>
                                    <div class="flex-grow">
                                        <p class="text-[10px] font-bold text-gray-500 uppercase leading-none mb-1">Criado por</p>
                                        <p class="text-xs font-extrabold text-gray-700">{{ $createdByUser ?? 'Via Site' }}</p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">Em: {{ \Carbon\Carbon::parse($schedule->created_at)->format('d/m/Y H:i') }}</p>
                                    </div>
                                </div>
           
                                @if($schedule->updated_at && $schedule->created_at != $schedule->updated_at)
                                <!-- Atualização -->
                                <div class="flex items-start gap-3">
                                    <div class="mt-1 w-2 h-2 rounded-full {{ $updatedByUser ? 'bg-indigo-500' : 'bg-gray-300' }}"></div>
                                    <div class="flex-grow">
                                        <p class="text-[10px] font-bold text-gray-500 uppercase leading-none mb-1">Última Atualização</p>
                                        <p class="text-xs font-extrabold text-gray-700">
                                            {{ $updatedByUser ?? 'Via Site' }}
                                        </p>
                                        <p class="text-[10px] text-gray-400 mt-0.5">
                                            
                                                Em: {{ \Carbon\Carbon::parse($schedule->updated_at)->format('d/m/Y H:i') }}
                                            
                                            
                                        </p>
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
                            <div class="flex items-center gap-4 p-4 bg-amber-50 rounded-2xl border border-amber-100 mt-4">
                                <div class="p-3 bg-amber-500 text-white rounded-xl shadow-lg animate-pulse-fast">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-amber-600 uppercase">Tempo de Espera</p>
                                    <p class="text-sm font-black text-amber-700">Aguardando pagamento há {{ $formatted }} minutos</p>
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
                    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-indigo-600 rounded-lg text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </div>
                            <h3 class="text-xl font-extrabold text-gray-800">Agendamentos Vinculados</h3>
                        </div>

                        @php
                            $countOthers = collect($otherSchedules)->where('id', '!=', $schedule->id)->count();
                        @endphp

                        @if($countOthers > 0)
                        <div class="p-4 mb-6 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-700 text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            O membro possui <span class="font-black">{{ $countOthers }} outro(s) agendamento(s)</span> neste dia. Selecione-os para aplicar a alteração em massa.
                        </div>
                        @else
                        <div class="p-4 mb-6 rounded-2xl bg-gray-50 border border-gray-100 text-gray-500 text-xs italic text-center">
                            Nenhum outro agendamento encontrado para este sócio nesta data.
                        </div>
                        @endif

                        <div class="space-y-3">
                            <!-- Card do Agendamento Atual (Travado) -->
                            <label class="relative flex items-center p-4 border-2 border-indigo-600 bg-indigo-50 rounded-2xl cursor-default shadow-sm">
                                <input type="checkbox" name="selected_reservations[]" value="{{ $schedule->id }}" checked onclick="return false;" class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                <div class="ml-4">
                                    <p class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-1">Reserva Principal</p>
                                    <p class="text-lg font-black text-indigo-900 leading-none">
                                        #{{ $schedule->id }} — {{ \Carbon\Carbon::parse($schedule->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($schedule->end_schedule)->format('H:i') }}
                                    </p>
                                    <p class="text-xs font-bold text-indigo-700 mt-1">{{ $place->name ?? 'Local Principal' }}</p>
                                </div>
                            </label>
                            <!-- Outros agendamentos -->
                            @foreach($otherSchedules as $other)
                                @if($other->id !== $schedule->id)
                                    @php
                                        $otherStatus = match((int)$other->status_id) {
                                            1 => ['label' => 'Confirmado', 'class' => 'bg-green-100 text-green-700'],
                                            3 => ['label' => 'Pendente', 'class' => 'bg-amber-100 text-amber-700'],
                                            0 => ['label' => 'Cancelado', 'class' => 'bg-red-100 text-red-700'],
                                            10 => ['label' => 'Antigo', 'class' => 'bg-gray-100 text-gray-600'],
                                            default => ['label' => '?', 'class' => 'bg-gray-100 text-gray-400'],
                                        };
                                        $isCanceledOther = $other->status_id == 0;
                                    @endphp

                                    @if($isCanceledOther || $isCanceled)
                                    <div class="relative flex items-center p-4 border-2 border-gray-100 rounded-2xl bg-gray-50 opacity-60 cursor-not-allowed">
                                        <div class="w-5 h-5 border-gray-300 rounded bg-gray-200"></div>
                                        <div class="ml-4">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Mesmo Sócio / Mesma Data</p>
                                            <p class="text-lg font-black text-gray-500 leading-none">
                                                #{{ $other->id }} — {{ \Carbon\Carbon::parse($other->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($other->end_schedule)->format('H:i') }}
                                            </p>
                                            <p class="text-[10px] font-bold text-red-500 mt-1 uppercase">Esse agendamento não pode ser alterado</p>
                                        </div>
                                        <div class="ml-auto text-right">
                                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase {{ $otherStatus['class'] }}">
                                                {{ $otherStatus['label'] }}
                                            </span>
                                        </div>
                                    </div>
                                    @else
                                    <label class="relative flex items-center p-4 border-2 border-gray-100 rounded-2xl cursor-pointer transition group hover:bg-gray-50 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                        <input type="checkbox" name="selected_reservations[]" value="{{ $other->id }}" class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                        <div class="ml-4">
                                            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Mesmo Sócio / Mesma Data</p>
                                            <p class="text-lg font-black text-gray-800 leading-none group-has-[:checked]:text-indigo-900">
                                                #{{ $other->id }} — {{ \Carbon\Carbon::parse($other->start_schedule)->format('H:i') }} às {{ \Carbon\Carbon::parse($other->end_schedule)->format('H:i') }}
                                            </p>
                                            <p class="text-xs font-bold text-gray-500 group-has-[:checked]:text-indigo-700 mt-1">{{ $other->place->name ?? 'Outro Local' }}</p>
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
                        <div class="p-4 mb-6 rounded-2xl bg-amber-50 border border-amber-100 text-amber-700 text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Este agendamento está com o pagamento <span class="font-black">PENDENTE</span>. Por favor aguarde.
                        </div>
                        @else
                        <div class="p-4 mb-6 rounded-2xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium leading-relaxed">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Este agendamento já está <span class="font-black">CANCELADO</span>. Não é possível realizar alterações.
                        </div>
                        @endif
                    @else
                    @can('edit.reservations')
                    <!-- Secção: Operação a Realizar -->
                    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2 bg-amber-500 rounded-lg text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </div>
                            <h3 class="text-xl font-extrabold text-gray-800">Operação Desejada</h3>
                        </div>
                        @if($schedule->status_id == 0)
                            <div class="p-4 mb-6 rounded-2xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium leading-relaxed">
                                <svg class="w-4 h-4 inline-block mr-1 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                Este agendamento já está <span class="font-black">CANCELADO</span>. Não é possível realizar alterações adicionais.
                            </div>
                        @else
                        <div class="space-y-4">
                            <div>
                                <label for="action_status" class="block text-xs font-black text-gray-400 uppercase mb-2 tracking-widest ml-1">Selecione o Novo Estado</label>
                                <select id="action_status" name="action_status" required onchange="handleActionChange(this)"
                                        class="block w-full py-4 px-4 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 outline-none transition font-extrabold text-gray-700">
                                    <option value="">O que deseja fazer?</option>
                                    {{-- <option value="1">✅ Confirmar Agendamento(s)</option> --}}
                                    <option value="0">❌ Cancelar Agendamento(s)</option>
                                </select>
                            </div>

                            <!-- OPÇÕES DINÂMICAS DE CANCELAMENTO -->
                            <div id="cancel-options" class="hidden animate-fadeIn space-y-3 bg-red-50 p-6 rounded-2xl border border-red-100">
                                <p class="text-[10px] font-black text-red-400 uppercase tracking-widest mb-2">Configurações de Cancelamento</p>
                                
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="confirm_cancel" required class="w-5 h-5 text-red-600 border-red-300 rounded focus:ring-red-500 transition">
                                    <span class="text-sm font-bold text-red-700 group-hover:text-red-900 transition">Confirmo que desejo cancelar permanentemente</span>
                                </label>

                                @if($isViaSite && !$isPending)
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="refund_payment" value="1" class="w-5 h-5 text-red-600 border-red-300 rounded focus:ring-red-500 transition">
                                    <span class="text-sm font-bold text-red-700 group-hover:text-red-900 transition">Solicitar estorno do valor pago (Agendamento via Site)</span>
                                </label>
                                @endif
                            </div>

                            <div class="pt-4 border-t border-gray-50">
                                <button type="submit" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest shadow-xl hover:bg-indigo-600 transition transform hover:scale-[1.01] active:scale-95">
                                    Salvar Alterações
                                </button>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endcan
                    @endif
                </form>
            </div>

        </div>
    </div>

    <script>
        function handleActionChange(select) {
            const cancelOptions = document.getElementById('cancel-options');
            const confirmCheckbox = document.querySelector('input[name="confirm_cancel"]');
            
            if (select.value === '0') {
                cancelOptions.classList.remove('hidden');
                confirmCheckbox.required = true;
            } else {
                cancelOptions.classList.add('hidden');
                confirmCheckbox.required = false;
            }
        }
    </script>
</x-app-layout>
