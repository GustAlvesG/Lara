<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Serviços / Contratos') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Página de tabela larga: usa toda a largura que a sidebar deixa livre.
         Travada em max-w-7xl, a tabela cortava as últimas colunas. --}}
    <div class="max-w-full mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Serviços / Contratos</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Serviços a serem realizados por freelancers e o estado das assinaturas.</p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('freelancer-services.bulk') }}" class="inline-flex items-center px-4 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-xl font-bold shadow border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    Em massa
                </a>

                <a href="{{ route('freelancer-services.create') }}" class="inline-flex items-center px-4 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Novo Serviço
                </a>
            </div>
        </div>

        @include('freelancer.services.partials.tabs')

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($services->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum serviço registrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3">Freelancer</th>
                                <th class="px-4 py-3">Função</th>
                                <th class="px-4 py-3">Evento/Local</th>
                                <th class="px-4 py-3">Período</th>
                                <th class="px-4 py-3">Duração</th>
                                <th class="px-4 py-3">Preço</th>
                                <th class="px-4 py-3">Contrato</th>
                                <th class="px-4 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($services as $service)
                            @php $exceeds = $excessFlags[$service->id] ?? false; @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ $exceeds ? 'bg-amber-50 dark:bg-amber-900/10' : '' }} {{ $service->isCancelled() ? 'opacity-60' : '' }}">
                                <td class="px-4 py-4">
                                    @if($exceeds)
                                        <span title="Freelancer com mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias" class="text-amber-500">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 font-semibold text-gray-900 dark:text-white">{{ $service->freelancer->name }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $service->functionFreelancer->name }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $service->location ?? '—' }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                    @if($service->start_date->ne($service->end_date))
                                        <span title="Termina no dia seguinte" class="text-amber-500">+1</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">{{ $service->formattedDuration() }}</td>
                                <td class="px-4 py-4 text-gray-700 dark:text-gray-300">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                <td class="px-4 py-4">
                                    <x-freelancer-signature-badge :service="$service" />
                                    {{-- Mesma informação da tarja da tela do contrato, em selo. --}}
                                    @if($service->isSignedAfterStart())
                                        <span class="block mt-1 text-xs font-bold text-rose-600 dark:text-rose-400 whitespace-nowrap"
                                              title="O turno começou em {{ $service->startsAt()->format('d/m/Y H:i') }} e a assinatura do freelancer foi registrada em {{ $service->freelancer_signed_at->format('d/m/Y H:i') }}. Tolerância de {{ \App\Models\FreelancerService::SIGNATURE_TOLERANCE_MINUTES }} minutos.">
                                            🕒 Assinado {{ $service->formattedSignatureDelay() }} após o início
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('freelancer-services.show', $service) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">
                                        {{ $service->canBeUpdated() ? 'Editar' : 'Ver' }}
                                    </a>
                                    @if($service->canBeDeleted())
                                        <form method="POST" action="{{ route('freelancer-services.destroy', $service) }}" class="inline"
                                              onsubmit="return confirm('Excluir este serviço?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Excluir</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
</x-app-layout>
