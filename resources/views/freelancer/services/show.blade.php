<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviço / Contrato') }}
        </h2>
    </x-slot>

@php $locked = !$service->canBeUpdated(); @endphp

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        @if($exceedsWeeklyLimit)
            <div class="p-4 bg-amber-100 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-amber-800 dark:text-amber-200 rounded-lg text-sm font-medium">
                ⚠️ Este freelancer possui mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços registrados numa janela de {{ \App\Models\FreelancerService::WEEKLY_WINDOW_DAYS }} dias em torno desta data.
                {{-- Quem liberou o excedente. Contratos anteriores à regra não têm o registro.
                     Sem autor: veio do código de e-mail, que vai para todos os
                     coordenadores do Comercial e não identifica qual deles ditou. --}}
                @if($service->weekly_limit_authorized_at)
                    <span class="block mt-1 font-normal">
                        @if($service->weeklyLimitAuthorizedBy)
                            Liberado por {{ $service->weeklyLimitAuthorizedBy->name }}
                            (coordenação do setor Comercial, PIN presencial)
                        @else
                            Liberado por código enviado aos coordenadores do setor Comercial
                        @endif
                        em {{ $service->weekly_limit_authorized_at->format('d/m/Y H:i') }}.
                    </span>
                @endif
            </div>
        @endif

        {{-- Contrato assinado depois de o turno já ter começado. A tolerância de
             30 min já está descontada; o tempo mostrado é o atraso em relação ao
             início. Só na web: o tablet é onde a assinatura acontece, e cobrar o
             atraso ali não muda mais nada. --}}
        @if($service->isSignedAfterStart())
            <div class="p-4 bg-rose-100 dark:bg-rose-900/30 border border-rose-300 dark:border-rose-700 text-rose-800 dark:text-rose-200 rounded-lg text-sm font-medium">
                🕒 Contrato assinado <b>após o início do serviço</b>.
                O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e a assinatura do
                freelancer foi registrada em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }} —
                <b>{{ $service->formattedSignatureDelay() }} depois</b>.
                <span class="block mt-1 font-normal">
                    O contrato deve ser assinado antes do início, com tolerância de
                    {{ \App\Models\FreelancerService::SIGNATURE_TOLERANCE_MINUTES }} minutos.
                </span>
            </div>
        @endif

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('freelancer-services.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $service->freelancer->name }}</h1>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">
                        {{ $service->functionFreelancer->name }} · {{ $service->location }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $service->formattedPeriod() }} ({{ $service->formattedDuration() }})
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        @if($service->createdBy)Cadastrado por {{ $service->createdBy->name }}@endif
                        @if($service->updatedBy && $service->updated_by !== $service->created_by) · Atualizado por {{ $service->updatedBy->name }}@endif
                    </p>
                </div>
            </div>

            <x-freelancer-signature-badge :service="$service" />
        </div>

        {{-- Painel de assinaturas --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Assinaturas do Contrato</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 rounded-xl border {{ $service->freelancer_signed_at ? 'border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600' }}">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Freelancer</p>
                    @if($service->freelancer_signed_at)
                        <p class="text-sm text-green-700 dark:text-green-400 font-semibold mt-1">
                            ✓ Assinado em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}
                        </p>
                        @if($service->freelancerSignedBy)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Atendimento conduzido por {{ $service->freelancerSignedBy->name }}.
                            </p>
                        @endif
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Aguardando assinatura (feita pelo bot no Telegram).</p>
                    @endif
                </div>

                <div class="p-4 rounded-xl border {{ $service->coordinator_signed_at ? 'border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-600' }}">
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Coordenador</p>
                    @if($service->coordinator_signed_at)
                        <p class="text-sm text-green-700 dark:text-green-400 font-semibold mt-1">
                            ✓ Assinado por {{ $service->coordinatorSignedBy?->name ?? '—' }}
                            em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}
                        </p>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            @if($service->isCancelled())
                                Contrato cancelado.
                            @else
                                {{-- A assinatura do coordenador é sempre desenhada; o painel só acompanha. --}}
                                Aguardando assinatura do coordenador do setor Comercial,
                                feita <b>no tablet</b> (Kiosk → modo coordenador).
                            @endif
                        </p>
                    @endif
                </div>
            </div>

            @if($service->isPaid())
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 text-sm text-emerald-800 dark:text-emerald-200">
                        Baixa de pagamento registrada
                        @if($service->paid_at) em {{ $service->paid_at->format('d/m/Y H:i') }} @endif
                        @if($service->paidBy) por {{ $service->paidBy->name }} @endif.
                    </div>
                </div>
            @endif

            @if($service->isCancelled())
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-gray-100 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-600 text-sm text-gray-600 dark:text-gray-300">
                        Contrato cancelado
                        @if($service->cancelled_at) em {{ $service->cancelled_at->format('d/m/Y H:i') }} @endif
                        @if($service->cancelledBy) por {{ $service->cancelledBy->name }} @endif.
                    </div>
                </div>
            @elseif($locked)
                <div class="px-6 pb-6">
                    <div class="p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-sm text-amber-800 dark:text-amber-200">
                        Este contrato já possui assinatura e por isso não pode mais ser alterado nem cancelado.
                    </div>
                </div>
            @endif
        </div>

        {{-- Documento do contrato --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-[#A00001]/10 text-[#A00001] shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Documento do Contrato</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Contrato Autônomo de Serviços de Freelancer preenchido com os dados deste serviço
                            @if($service->freelancer_signed_at) e a assinatura registrada @endif.
                        </p>
                    </div>
                </div>
                <a href="{{ route('freelancer-services.document', $service) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    Abrir documento
                </a>
            </div>

            @if($service->freelancer_signature_path)
                <div class="px-6 pb-6">
                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Assinatura do freelancer</p>
                    <div class="inline-block bg-white border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                        <img src="{{ route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'freelancer']) }}"
                             alt="Assinatura de {{ $service->freelancer->name }}" class="h-20 w-auto object-contain">
                    </div>
                </div>
            @endif

            {{-- Toda assinatura de coordenador é desenhada no tablet. Contratos antigos,
                 assinados pelo painel antes da mudança, não têm traço. --}}
            @if($service->coordinator_signature_path)
                <div class="px-6 pb-6">
                    <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Assinatura do coordenador</p>
                    <div class="inline-block bg-white border border-gray-200 dark:border-gray-600 rounded-lg p-2">
                        <img src="{{ route('freelancer-services.signature', ['freelancerService' => $service->id, 'party' => 'coordinator']) }}"
                             alt="Assinatura de {{ $service->coordinatorSignedBy->name ?? 'coordenador' }}" class="h-20 w-auto object-contain">
                    </div>
                </div>
            @endif
        </div>

        {{-- Dados do serviço --}}
        <form action="{{ route('freelancer-services.update', $service) }}" method="POST">
            @csrf
            @method('PUT')

            @include('freelancer.services.partials.form', ['locked' => $locked])

            @unless($locked)
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                        Salvar Alterações
                    </button>
                </div>
            @endunless
        </form>

        {{-- Ações destrutivas --}}
        @if($canCancel || $service->canBeDeleted())
            <div class="flex flex-wrap justify-end gap-3">
                @if($canCancel)
                    <form method="POST" action="{{ route('freelancer-services.cancel', $service) }}"
                          onsubmit="return confirm('Cancelar este contrato? Essa ação não pode ser desfeita.')">
                        @csrf
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition border border-amber-200 dark:border-amber-700">
                            Cancelar Contrato
                        </button>
                    </form>
                @endif

                @if($service->canBeDeleted())
                    <form method="POST" action="{{ route('freelancer-services.destroy', $service) }}"
                          onsubmit="return confirm('Excluir este serviço permanentemente?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                            Excluir Serviço
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</div>
</x-app-layout>
