<x-app-layout>

    @php
        // Regras individuais separadas por período de vigência. As fora de
        // vigência vão para uma seção própria, apenas para consulta.
        [$activeRules, $expiredRules] = $worker->rules->partition->isWithinValidityPeriod();
    @endphp

    <div class="max-w-4xl mx-auto">

        <!-- Botão Voltar -->
        <div class="my-8 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.show', $company->id) }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 dark:text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 border border-gray-100 dark:border-gray-700 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Perfil do Funcionário</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('company.worker.edit', [$company->id, $worker->id]) }}"
                   class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-bold text-sm shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Editar
                </a>
                <form action="{{ route('company.worker.destroy', [$company->id, $worker->id]) }}" method="POST"
                      onsubmit="return confirm('Deseja realmente remover este funcionário?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-4 py-2 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800 text-red-600 dark:text-red-400 rounded-lg font-bold text-sm shadow-sm hover:bg-red-100 dark:hover:bg-red-900/30 transition">
                        Excluir
                    </button>
                </form>
            </div>
        </div>

        <!-- Perfil Card -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl border border-gray-100 dark:border-gray-700 overflow-hidden mb-8">
            <div class="h-24 bg-indigo-600"></div>
            <div class="px-8 pb-8">
                <div class="flex items-end gap-6 -mt-12 mb-6">
                    @if($worker->image)
                        <img src="{{ asset('images/' . $worker->image) }}" alt="Foto de {{ $worker->name }}"
                             class="w-24 h-24 rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl object-cover bg-white">
                    @else
                        <div class="w-24 h-24 rounded-2xl border-4 border-white dark:border-gray-800 shadow-xl bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-400 text-3xl font-black">
                            {{ strtoupper(substr($worker->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="pb-2">
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white">{{ $worker->name }}</h2>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">{{ ucfirst($worker->position) }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        {{ $worker->email ?? '—' }}
                    </div>
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        {{ $worker->telephone ?? '—' }}
                    </div>
                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 gap-2">
                        <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2"></path>
                        </svg>
                        CPF: {{ $worker->document ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Regras Individuais -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
                <h3 class="text-lg font-extrabold text-gray-800 dark:text-white">Regras de Acesso Individuais</h3>
                <a href="{{ route('company.worker.rules.create', [$company->id, $worker->id]) }}"
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg font-bold text-xs shadow-md hover:bg-indigo-700 transition">
                    Nova Regra Individual
                </a>
            </div>

            <div class="p-6 space-y-4">
                @if($worker->rules->isNotEmpty())
                    <div class="flex items-start gap-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs font-medium">
                        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span>Este funcionário possui regras individuais. As regras gerais da empresa <strong>não se aplicam</strong> a ele — apenas as regras abaixo determinam o acesso.</span>
                    </div>
                @endif

                @forelse($activeRules as $rule)
                    @include('companies.rules.partials.card', ['rule' => $rule, 'companyId' => $company->id])
                @empty
                    <p class="text-gray-400 dark:text-gray-500 italic text-sm">Nenhuma regra individual vigente. Este funcionário segue as regras gerais da empresa.</p>
                @endforelse
            </div>
        </div>

        <!-- Regras Individuais Fora de Vigência (consulta) -->
        @if($expiredRules->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden mt-8">
                <div class="flex items-center gap-2 px-6 py-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30">
                    <h3 class="text-lg font-extrabold text-gray-800 dark:text-white">Regras Fora de Vigência</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500">(somente consulta)</span>
                </div>
                <div class="p-6 space-y-4">
                    @foreach($expiredRules as $rule)
                        @include('companies.rules.partials.card', ['rule' => $rule, 'companyId' => $company->id, 'expired' => true])
                    @endforeach
                </div>
            </div>
        @endif

    </div>

</x-app-layout>
