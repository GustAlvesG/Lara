<x-app-layout>
<div class="py-6">
    {{-- Formulário + tabela de contratos: sobe de 5xl para 7xl. Largura total
         estragaria a leitura do formulário; 5xl cortava a tabela. --}}
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        @unless($freelancer->hasCompleteContractData())
            <div class="rounded-2xl border border-warn/40 bg-warn-soft p-5 flex items-start gap-3">
                <span class="text-warn text-xl leading-none">⚠️</span>
                <div>
                    <p class="font-bold text-warn">Cadastro incompleto — geração de contrato bloqueada</p>
                    <p class="text-sm text-warn mt-1">
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
                    <a href="{{ route('freelancers.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    </a>
                    <div>
                        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">{{ $freelancer->name }}</h1>
                        <p class="text-ink-2 font-medium">Edite os dados do freelancer.</p>
                        <p class="text-xs text-ink-3 mt-1">
                            @if($freelancer->createdBy)Cadastrado por {{ $freelancer->createdBy->name }}@endif
                            @if($freelancer->updatedBy && $freelancer->updated_by !== $freelancer->created_by) · Atualizado por {{ $freelancer->updatedBy->name }}@endif
                        </p>
                    </div>
                </div>

                <button type="submit" class="inline-flex items-center px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                    Salvar Alterações
                </button>
            </div>

            @include('freelancer.freelancers.partials.form')
        </form>

        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line bg-subtle flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Serviços / Contratos</h2>
                @can(\App\Authorization\Permissions::FREELANCERS_SERVICOS_GERENCIAR)
                <a href="{{ route('freelancer-services.create') }}" class="text-sm font-bold text-danger hover:underline">+ Novo serviço</a>
                @endcan
            </div>

            @if($freelancer->freelancerServices->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-ink-2">Nenhum serviço registrado para este freelancer.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                            <tr>
                                <th class="px-6 py-3"></th>
                                <th class="px-6 py-3">Nº</th>
                                <th class="px-6 py-3">Função</th>
                                <th class="px-6 py-3">Evento/Local</th>
                                <th class="px-6 py-3">Período</th>
                                @can(\App\Authorization\Permissions::FREELANCERS_SERVICOS_GERENCIAR)
                                <th class="px-6 py-3">Preço</th>
                                @endcan
                                <th class="px-6 py-3">Contrato</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($freelancer->freelancerServices as $service)
                            @php $exceeds = $excessFlags[$service->id] ?? false; @endphp
                            <tr class="hover:bg-subtle transition {{ $exceeds ? 'bg-warn-soft' : '' }}">
                                <td class="px-6 py-4">
                                    @if($exceeds)
                                        <span title="Mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias" class="text-warn">⚠️</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-ink-3 whitespace-nowrap">#{{ $service->id }}</td>
                                <td class="px-6 py-4 text-ink font-medium">
                                    {{ $service->functionFreelancer->name }}
                                    <x-freelancer-kind-badge :service="$service" class="ml-1 align-middle" />
                                </td>
                                <td class="px-6 py-4 text-ink">
                                    {{ $service->location ?? '—' }}
                                    @if(filled($service->description))
                                        <span class="block max-w-[16rem] truncate text-xs text-ink-3" title="{{ $service->description }}">{{ $service->description }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-ink whitespace-nowrap">
                                    {{ $service->start_date->format('d/m/Y') }}
                                    <span class="text-ink-3">
                                        {{ substr($service->start_time, 0, 5) }}–{{ substr($service->end_time, 0, 5) }}
                                    </span>
                                    @if($service->start_date->ne($service->end_date))
                                        <span title="Termina no dia seguinte" class="text-warn">+1</span>
                                    @endif
                                </td>
                                {{-- Valor é de quem gerencia os serviços; o cadastro (Secretaria) não vê. --}}
                                @can(\App\Authorization\Permissions::FREELANCERS_SERVICOS_GERENCIAR)
                                <td class="px-6 py-4 text-ink">R$ {{ number_format($service->price, 2, ',', '.') }}</td>
                                @endcan
                                <td class="px-6 py-4">
                                    <x-freelancer-signature-badge :service="$service" />
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @can(\App\Authorization\Permissions::FREELANCERS_SERVICOS_GERENCIAR)
                                    <a href="{{ route('freelancer-services.show', $service) }}" class="text-grena-ink hover:underline font-medium text-xs">Ver / Editar</a>
                                    @endcan
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
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Freelancer
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
