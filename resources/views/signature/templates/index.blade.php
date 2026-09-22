<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Modelos de Documento') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Modelos</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">
                    O texto dos termos, fichas e contratos assinados no tablet. Revisar um modelo cria a
                    versão seguinte — os documentos já emitidos continuam com o texto que imprimiram.
                </p>
            </div>

            <a href="{{ route('signature-templates.create') }}" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Modelo
            </a>
        </div>

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            @if($templates->isEmpty())
                <div class="p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400">Nenhum modelo cadastrado.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-6 py-3">Modelo</th>
                                <th class="px-6 py-3">Versão</th>
                                <th class="px-6 py-3">Identidade</th>
                                <th class="px-6 py-3">Foto</th>
                                <th class="px-6 py-3">Documentos</th>
                                <th class="px-6 py-3">Situação</th>
                                <th class="px-6 py-3 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($templates as $template)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $template->name }}</div>
                                    @if($template->description)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $template->description }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">v{{ $template->version }}</td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300 text-xs">
                                    {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$template->identity_check] ?? $template->identity_check }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300 text-xs">
                                    {{ $template->requires_photo ? 'Exigida' : 'Não' }}
                                </td>
                                <td class="px-6 py-4 text-gray-700 dark:text-gray-300">
                                    {{ $usos[$template->id] ?? 0 }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($template->active)
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">Ativo</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">Inativo</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
                                    <a href="{{ route('signature-templates.show', $template) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Ver</a>
                                    <a href="{{ route('signature-templates.edit', $template) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium text-xs">Revisar</a>
                                    <form method="POST" action="{{ route('signature-templates.destroy', $template) }}" class="inline"
                                          onsubmit="return confirm('Excluir (ou desativar, se já usado) o modelo \'{{ $template->name }}\'?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline font-medium text-xs">Excluir</button>
                                    </form>
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
