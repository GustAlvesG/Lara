<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Lote #{{ $batch->id }}</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    Montado por {{ $batch->createdBy->name ?? '—' }} ·
                    enviado em {{ $batch->sent_at?->format('d/m/Y H:i') ?? 'ainda não enviado' }}
                    @if($batch->isReviewed())
                        · analisado por {{ $batch->reviewedBy->name ?? '—' }} em {{ $batch->reviewed_at?->format('d/m/Y H:i') }}
                    @endif
                </p>
            </div>
            @php
                $tomLote = match (true) {
                    $batch->isDirectorApproved() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                    $batch->isDirectorRejected(), $batch->isClosed() => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                };
            @endphp
            <span class="px-4 py-2 rounded-xl text-sm font-bold {{ $tomLote }}">
                {{ $batch->statusLabel() }}
            </span>
        </div>

        @include('partials.alerts')

        @if($errors->any())
            <div class="mb-6 bg-red-600 text-white px-6 py-4 rounded-2xl shadow-xl">
                <p class="font-extrabold">Confira o formulário</p>
                <ul class="mt-2 text-sm list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            $total = $batch->services->sum('price');
            $aprovadosPelaGerencia = $batch->services->filter->isManagerApproved();
        @endphp

        {{-- ============ DIRETORIA ============ --}}
        @if($batch->isAwaitingDirector())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border-2 border-amber-300 dark:border-amber-700 mb-8 overflow-hidden">
                <div class="px-6 py-5 bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800">
                    <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">Aprovação da diretoria</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                        {{ $aprovadosPelaGerencia->count() }} contrato(s) ·
                        R$ {{ number_format($aprovadosPelaGerencia->sum('price'), 2, ',', '.') }}
                        aguardando o aval do diretor.
                    </p>
                </div>

                <div class="px-6 py-5">
                    @if($batch->director_notified_at)
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-5 text-sm">
                            <p class="text-gray-600 dark:text-gray-300">
                                E-mail enviado para <b>{{ $batch->director_email }}</b>
                                em {{ $batch->director_notified_at->format('d/m/Y H:i') }}.
                            </p>
                            <form action="{{ route('freelancer-batches.director.notify', $batch) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs font-bold text-[#A00001] dark:text-red-400 hover:underline">
                                    Reenviar e-mail
                                </button>
                            </form>
                        </div>

                        <form action="{{ route('freelancer-batches.director.decision', $batch) }}" method="POST"
                              onsubmit="return confirm('Registrar a decisão da diretoria? A decisão é definitiva.')">
                            @csrf
                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                                O diretor recebeu <b>dois códigos</b> por e-mail: um aprova, o outro recusa o lote
                                inteiro. Peça a ele o código e digite abaixo — o próprio código diz qual foi a decisão.
                            </p>

                            <div class="flex flex-wrap items-end gap-4">
                                <div>
                                    <label for="pin" class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                                        Código informado pelo diretor
                                    </label>
                                    <input type="text" id="pin" name="pin" inputmode="numeric" autocomplete="off"
                                           maxlength="{{ \App\Models\FreelancerServiceBatch::PIN_LENGTH }}"
                                           pattern="\d{{ '{' . \App\Models\FreelancerServiceBatch::PIN_LENGTH . '}' }}"
                                           placeholder="{{ str_repeat('•', \App\Models\FreelancerServiceBatch::PIN_LENGTH) }}" required
                                           class="w-48 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white
                                                  text-2xl font-extrabold tracking-[0.4em] text-center tabular-nums
                                                  focus:border-[#A00001] focus:ring-[#A00001]">
                                </div>
                                <div class="flex-1 min-w-[220px]">
                                    <label for="note" class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                                        Observação (opcional)
                                    </label>
                                    <input type="text" id="note" name="note" maxlength="255"
                                           placeholder="Ex.: informado por telefone em {{ now()->format('d/m') }}"
                                           class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm
                                                  focus:border-[#A00001] focus:ring-[#A00001]">
                                </div>
                                <button type="submit"
                                        class="px-6 py-3 rounded-xl text-sm font-bold text-white bg-[#A00001] hover:bg-[#7c0001] shadow transition">
                                    Registrar decisão
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
                            A diretoria ainda <b>não foi avisada</b> deste lote.
                            @if($directorEmail)
                                O e-mail com os códigos de aprovação vai para <b>{{ $directorEmail }}</b>.
                            @else
                                <span class="text-[#A00001] dark:text-red-400">
                                    Nenhum e-mail de diretoria configurado — defina <code>FREELANCER_DIRECTOR_EMAIL</code> no .env.
                                </span>
                            @endif
                        </p>
                        <form action="{{ route('freelancer-batches.director.notify', $batch) }}" method="POST">
                            @csrf
                            <button type="submit" @disabled(!$directorEmail)
                                    class="px-6 py-3 rounded-xl text-sm font-bold text-white bg-[#A00001] hover:bg-[#7c0001] shadow transition
                                           disabled:opacity-40 disabled:cursor-not-allowed">
                                Enviar à diretoria
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @elseif($batch->isDirectorApproved() || $batch->isDirectorRejected())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 mb-8 px-6 py-5">
                <h2 class="text-lg font-extrabold text-gray-900 dark:text-white mb-1">Decisão da diretoria</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-bold {{ $batch->isDirectorApproved() ? 'text-emerald-600 dark:text-emerald-400' : 'text-[#A00001] dark:text-red-400' }}">
                        {{ $batch->isDirectorApproved() ? 'Aprovado' : 'Recusado' }}
                    </span>
                    em {{ $batch->director_decided_at?->format('d/m/Y H:i') }},
                    código informado por {{ $batch->directorDecidedBy->name ?? '—' }}
                    @if($batch->director_email) · e-mail enviado a {{ $batch->director_email }} @endif
                </p>
                @if($batch->director_note)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2"><b>Observação:</b> {{ $batch->director_note }}</p>
                @endif
            </div>
        @endif

        <form action="{{ route('freelancer-batches.review', $batch) }}" method="POST">
            @csrf

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-extrabold text-gray-900 dark:text-white">{{ $batch->services->count() }} contrato(s)</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total do lote: R$ {{ number_format($total, 2, ',', '.') }}</p>
                    </div>
                    @if($canReview)
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Tudo começa aprovado. Marque <b>Recusar</b> só no que voltar para o coordenador.
                        </p>
                    @endif
                </div>

                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($batch->services as $service)
                        <div class="px-6 py-5" x-data="{ decision: '{{ $service->isManagerRejected() ? 'reject' : 'approve' }}' }">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-extrabold text-gray-900 dark:text-white">{{ $service->freelancer->name ?? '—' }}</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-300">
                                        {{ $service->functionFreelancer->name ?? '—' }} · {{ $service->location }}
                                    </p>
                                    <p class="text-xs text-gray-400 tabular-nums mt-1">
                                        {{ $service->formattedPeriod() }} · {{ $service->formattedDuration() }}
                                    </p>
                                    @if(filled($service->description))
                                        <p class="text-xs text-gray-600 dark:text-gray-300 mt-2 whitespace-pre-line bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700 rounded-lg px-3 py-2">
                                            <span class="font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wide text-[10px] block mb-0.5">Descrição / justificativa</span>
                                            {{ $service->description }}
                                        </p>
                                    @endif
                                    <a href="{{ route('freelancer-services.document', $service) }}" target="_blank"
                                       class="inline-block mt-2 text-xs font-bold text-[#A00001] dark:text-red-400 hover:underline">
                                        Abrir contrato assinado
                                    </a>
                                </div>

                                <div class="text-right shrink-0">
                                    <p class="text-xl font-extrabold text-gray-900 dark:text-white tabular-nums">
                                        R$ {{ number_format($service->price, 2, ',', '.') }}
                                    </p>

                                    @if($canReview)
                                        <div class="mt-3 inline-flex rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden">
                                            <label class="px-4 py-2 text-sm font-bold cursor-pointer transition"
                                                   :class="decision === 'approve' ? 'bg-emerald-600 text-white' : 'text-gray-500 dark:text-gray-400'">
                                                <input type="radio" class="sr-only" value="approve" x-model="decision"
                                                       name="decisions[{{ $service->id }}][decision]">
                                                Aprovar
                                            </label>
                                            <label class="px-4 py-2 text-sm font-bold cursor-pointer transition"
                                                   :class="decision === 'reject' ? 'bg-[#A00001] text-white' : 'text-gray-500 dark:text-gray-400'">
                                                <input type="radio" class="sr-only" value="reject" x-model="decision"
                                                       name="decisions[{{ $service->id }}][decision]">
                                                Recusar
                                            </label>
                                        </div>
                                    @else
                                        @php
                                            $tom = match (true) {
                                                $service->isDirectorApproved() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                                $service->isDirectorRejected(), $service->isManagerRejected() => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                                default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                            };
                                        @endphp
                                        <span class="mt-3 inline-block px-3 py-1 rounded-full text-xs font-bold {{ $tom }}">
                                            {{ $service->approvalLabel() }}
                                        </span>
                                        @if($service->isManagerApproved() && $service->managerApprovedBy)
                                            <p class="mt-1 text-xs text-gray-400">gerência: {{ $service->managerApprovedBy->name }}</p>
                                        @elseif($service->isManagerRejected() && $service->managerRejectedBy)
                                            <p class="mt-1 text-xs text-gray-400">gerência: {{ $service->managerRejectedBy->name }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            @if($canReview)
                                <div class="mt-3" x-show="decision === 'reject'" x-cloak>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
                                        Motivo da recusa
                                    </label>
                                    <input type="text" name="decisions[{{ $service->id }}][reason]" maxlength="255"
                                           value="{{ old('decisions.' . $service->id . '.reason', $service->manager_rejection_reason) }}"
                                           placeholder="Ex.: horário divergente do informado pelo evento"
                                           class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm focus:border-[#A00001] focus:ring-[#A00001]">
                                    <p class="mt-1 text-xs text-gray-400">O coordenador vê este motivo ao remontar o lote.</p>
                                </div>
                            @elseif($service->isManagerRejected() && $service->manager_rejection_reason)
                                <p class="mt-3 text-sm text-red-600 dark:text-red-400">
                                    <b>Motivo da recusa:</b> {{ $service->manager_rejection_reason }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if($canReview)
                    <div class="px-6 py-5 border-t border-gray-100 dark:border-gray-700 flex items-center justify-end gap-3">
                        <a href="{{ route('freelancer-batches.queue') }}"
                           class="px-4 py-2.5 rounded-xl text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                            Voltar
                        </a>
                        <button type="submit"
                                onclick="return confirm('Concluir a análise do lote? A decisão é definitiva.')"
                                class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-[#A00001] hover:bg-[#7c0001] shadow transition">
                            Concluir análise do lote
                        </button>
                    </div>
                @endif
            </div>
        </form>

    </div>
</div>
</x-app-layout>
