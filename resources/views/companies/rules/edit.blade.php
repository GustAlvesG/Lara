<x-app-layout>

    <div>
        <x-crud.form :formRoute="route('company.rules.update', [$company->id, $rule->id])" :formMethod="'POST'" :hasImageSection="false">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <a href="{{ $rule->company_worker_id ? route('company.worker.show', [$company->id, $rule->company_worker_id]) : route('company.show', $company->id) }}"
                       class="p-2 bg-white rounded-xl shadow-md text-gray-400 hover:text-indigo-600 border border-gray-100 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Editar Regra de Acesso</h1>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">
                            {{ $rule->company_worker_id && $rule->worker ? 'Regra individual de ' . $rule->worker->name : 'Regra geral da empresa ' . $company->name }}
                        </p>
                        <div class="flex flex-wrap gap-4 mt-1 text-xs text-gray-400 dark:text-gray-500">
                            <span>
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Criado por:</span>
                                {{ $rule->creator?->name ?? '—' }}
                                @if($rule->created_at)
                                    em {{ $rule->created_at->format('d/m/Y H:i') }}
                                @endif
                            </span>
                            <span>
                                <span class="font-semibold text-gray-600 dark:text-gray-400">Última alteração por:</span>
                                {{ $rule->editor?->name ?? '—' }}
                                @if($rule->updated_at && $rule->updated_at->ne($rule->created_at))
                                    em {{ $rule->updated_at->format('d/m/Y H:i') }}
                                @endif
                            </span>
                        </div>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">
                @method('PUT')
                @include('companies.rules.partials.form', ['rule' => $rule, 'company' => $company])
            </x-slot>

        </x-crud.form>
    </div>

</x-app-layout>
