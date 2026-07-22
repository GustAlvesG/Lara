<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Freelancers') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Freelancers</h1>
                <p class="text-gray-500 dark:text-gray-400 font-medium">Cadastro interno de freelancers.</p>
            </div>

            <a href="{{ route('freelancers.create') }}" class="inline-flex items-center px-6 py-3 bg-[#A00001] text-white rounded-xl font-bold shadow-lg hover:bg-[#800000] transition duration-150 transform hover:scale-[1.02]">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Novo Freelancer
            </a>
        </div>

        @include('partials.alerts')

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-6 mb-8 border border-gray-100 dark:border-gray-700">
            <form method="GET" action="{{ route('freelancers.index') }}" class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </span>
                <input type="text" name="q" value="{{ $search }}" placeholder="Buscar por nome ou CPF..."
                       class="w-full pl-10 pr-4 py-3 border border-gray-200 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 transition duration-150 outline-none shadow-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white dark:placeholder-gray-500">
            </form>
        </div>

        @if($freelancers->isEmpty())
            <div class="bg-white dark:bg-gray-800 p-12 text-center rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium">Nenhum freelancer encontrado.</p>
                <a href="{{ route('freelancers.create') }}" class="mt-4 inline-flex items-center px-4 py-2 bg-[#A00001] text-white rounded-lg font-bold hover:bg-[#800000] transition">
                    Cadastrar freelancer
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($freelancers as $freelancer)
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl border {{ $freelancer->exceeds_weekly_limit ? 'border-amber-300 dark:border-amber-700' : 'border-gray-100 dark:border-gray-700' }} overflow-hidden">
                    <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-start justify-between bg-gray-50/50 dark:bg-gray-700/50">
                        <div>
                            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white flex items-center gap-2 flex-wrap">
                                {{ $freelancer->name }}
                                @if($freelancer->exceeds_weekly_limit)
                                    <span title="Mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias"
                                          class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-700">
                                        ⚠️ excesso de serviços
                                    </span>
                                @endif
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">CPF: {{ $freelancer->cpf }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">PIX: {{ $freelancer->pix_key }}</p>
                            @if($freelancer->email)
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $freelancer->email }}</p>
                            @endif
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <a href="{{ route('freelancers.show', $freelancer) }}" class="p-2 text-indigo-600 dark:text-indigo-400 hover:bg-white dark:hover:bg-gray-600 rounded-lg transition border border-transparent hover:border-indigo-100 dark:hover:border-indigo-900" title="Editar">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </a>
                            <form method="POST" action="{{ route('freelancers.destroy', $freelancer) }}"
                                  onsubmit="return confirm('Excluir o freelancer \'{{ $freelancer->name }}\' permanentemente?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 text-red-700 dark:text-red-400 hover:bg-white dark:hover:bg-gray-600 rounded-lg transition border border-transparent hover:border-red-100 dark:hover:border-red-900" title="Excluir">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-500">ID: #{{ $freelancer->id }}</span>
                        <span class="text-xs font-medium text-gray-400 dark:text-gray-600">{{ $freelancer->freelancer_services_count }} serviço(s)</span>
                    </div>
                </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
</x-app-layout>
