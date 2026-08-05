<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Freelancer') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    {{-- Formulário + tabela de contratos: sobe de 5xl para 7xl. Largura total
         estragaria a leitura do formulário; 5xl cortava a tabela. --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        @unless($freelancer->hasCompleteContractData())
            <div class="rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-5 flex items-start gap-3">
                <span class="text-amber-500 text-xl leading-none">⚠️</span>
                <div>
                    <p class="font-bold text-amber-800 dark:text-amber-300">Cadastro incompleto — geração de contrato bloqueada</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                        Faltam: {{ implode(', ', $freelancer->missingContractFieldLabels()) }}.
                        Complete os dados abaixo e salve para liberar a geração de contratos deste freelancer.
                    </p>
                </div>
            </div>
        @endunless

        <form action="{{ route('freelancers.update', $freelancer) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('freelancers.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $freelancer->name }}</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Edite os dados do freelancer.</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            @if($freelancer->createdBy)Cadastrado por {{ $freelancer->createdBy->name }}@endif
                            @if($freelancer->updatedBy && $freelancer->updated_by !== $freelancer->created_by) · Atualizado por {{ $freelancer->updatedBy->name }}@endif
                        </p>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>

            @include('freelancer.freelancers.partials.form')
        </form>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Serviços / Contratos</h2>
                <a href="{{ route('freelancer-services.create') }}" class="text-sm font-bold text-red-700 dark:text-red-400 hover:underline">+ Novo serviço</a>
            </div>

            @if($freelancer->freelancerServices->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum serviço registrado para este freelancer.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3">Nº</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3">Evento/Local</th>
                                <th class="px-6 py-3">Período</th>
                                <th class="px-6 py-3">Preço</th>
                                <th class="px-6 py-3">Contrato</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($freelancer->freelancerServices as $service)
                            @php $exceeds = $excessFlags[$service->id] ?? false; @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition {{ $exceeds ? 'bg-amber-50 dark:bg-amber-900/10' : '' }}">
                                <td class="px-6 py-4">
                                    @if($exceeds)
                                        <span title="Mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias" class="text-amber-500">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">#{{ $service->id }}</td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">
                                    {{ $service->functionFreelancer->name }}
                                    <x-freelancer-kind-badge :service="$service" class="ml-1 align-middle" />
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $service->location ?? '—' }}
                                    @if(filled($service->description))
                                        <span class="block max-w-[16rem] truncate text-xs text-gray-400 dark:text-gray-500" title="{{ $service->description }}">{{ $service->description }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-gray-400 dark:text-gray-500">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                    @if($service->start_date->ne($service->end_date))
                                        <span title="Termina no dia seguinte" class="text-amber-500">+1</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                <td class="px-6 py-4">
                                    <x-freelancer-signature-badge :service="$service" />
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('freelancer-services.show', $service) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Ver / Editar</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('freelancers.destroy', $freelancer) }}"
                  onsubmit="return confirm('Excluir o freelancer \'{{ $freelancer->name }}\' permanentemente?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Freelancer
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
