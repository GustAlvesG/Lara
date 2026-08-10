@php
    /**
     * Tabela de contratos do acompanhamento. Recebe:
     *   $services         coleção de FreelancerService
     *   $canOpenServices  o usuário alcança a tela do contrato? (permissão
     *                     `manage freelancers` — o Comercial nem sempre tem)
     *   $showBatch        mostrar a coluna do lote (nas listas fora de lote,
     *                     ela diz de qual lote o contrato voltou)
     *
     * A coluna que importa é a última: a etapa. As outras existem para o
     * atendente reconhecer o contrato quando o freelancer liga perguntando.
     */
    $showBatch = $showBatch ?? false;
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
            <tr>
                <th class="px-4 py-3 text-left font-bold">Contrato</th>
                <th class="px-4 py-3 text-left font-bold">Freelancer</th>
                <th class="px-4 py-3 text-left font-bold">Função / Local</th>
                <th class="px-4 py-3 text-left font-bold">Turno</th>
                <th class="px-4 py-3 text-right font-bold">Valor</th>
                @if($showBatch)
                    <th class="px-4 py-3 text-left font-bold">Lote</th>
                @endif
                <th class="px-4 py-3 text-left font-bold">Etapa</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            @if($services->isEmpty())
                <tr>
                    <td colspan="{{ $showBatch ? 7 : 6 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum contrato aqui — o lote está vazio.
                    </td>
                </tr>
            @endif
            @foreach($services as $service)
                @php($stage = $service->trackingStage())
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if($canOpenServices)
                            <a href="{{ route('freelancer-services.show', $service) }}"
                               class="font-mono font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                #{{ $service->id }}
                            </a>
                        @else
                            <span class="font-mono font-bold text-gray-500 dark:text-gray-400">#{{ $service->id }}</span>
                        @endif
                        @if($service->kindLabel())
                            <span class="block text-[11px] font-bold text-gray-400 dark:text-gray-500">{{ $service->kindLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-900 dark:text-white font-semibold">
                        {{ $service->freelancer->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                        {{ $service->functionFreelancer->name ?? '—' }}
                        <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $service->location }}</span>
                    </td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 whitespace-nowrap tabular-nums">
                        {{ $service->start_date?->format('d/m/Y') ?? '—' }}
                        <span class="text-gray-400 dark:text-gray-500">
                            {{ substr((string) $service->start_time, 0, 5) }}–{{ substr((string) $service->end_time, 0, 5) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white whitespace-nowrap tabular-nums">
                        R$ {{ number_format((float) $service->price, 2, ',', '.') }}
                    </td>
                    @if($showBatch)
                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $service->batch_id ? '#' . $service->batch_id : '—' }}
                        </td>
                    @endif
                    <td class="px-4 py-3">
                        @include('freelancer.services.partials.stage-badge', [
                            'stage' => $stage,
                            'label' => $service->trackingStageLabel(),
                            'size' => 'sm',
                        ])
                        {{-- O "por quê" das duas etapas que travam a fila: quem recusou
                             e por qual motivo, para o Comercial não precisar perguntar. --}}
                        @if($stage === 'manager_rejected' && $service->manager_rejection_reason)
                            <span class="block mt-1 text-[11px] text-red-600 dark:text-red-400">
                                {{ $service->manager_rejection_reason }}
                            </span>
                        @endif
                        @if($stage === 'paid' && $service->paid_at)
                            <span class="block mt-1 text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">
                                {{ $service->paid_at->format('d/m/Y') }}
                            </span>
                        @endif
                        @if($stage === 'awaiting_signatures')
                            <span class="block mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                {{ $service->signatureLabel() }}
                            </span>
                        @endif
                        {{-- Aqui não falta ninguém assinar: falta o relógio. O horário
                             exato evita a pergunta "e por que esse não anda?". --}}
                        @if($stage === 'awaiting_release' && $service->releasesAt())
                            <span class="block mt-1 text-[11px] text-gray-400 dark:text-gray-500 tabular-nums">
                                Vai à coordenação em {{ $service->releasesAt()->format('d/m/Y \à\s H:i') }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
