<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Equipe') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.equipes.index') }}" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-md text-gray-400 hover:text-emerald-600 dark:hover:text-emerald-400 border border-gray-100 dark:border-gray-700 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $equipe->nome }}
                    @if($equipe->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Criado em campo</span>
                    @endif
                </h1>
            </div>
        </div>

        {{-- Logo --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Logo</h2>
            <div class="flex items-center gap-6">
                @if($equipe->logoUrl())
                    <img src="{{ $equipe->logoUrl() }}" class="w-24 h-24 rounded-xl object-cover border border-gray-200 dark:border-gray-600">
                @else
                    <div class="w-24 h-24 rounded-xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-xs text-gray-400">sem logo</div>
                @endif
                <div class="flex flex-col gap-2">
                    <form action="{{ route('placar.equipes.logo.store', $equipe) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="arquivo" accept="image/jpeg,image/png,image/webp" required class="text-sm">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition">Enviar</button>
                    </form>
                    @if($equipe->logo_path)
                    <form action="{{ route('placar.equipes.logo.destroy', $equipe) }}" method="POST" onsubmit="return confirm('Remover a logo?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-red-600 dark:text-red-400 hover:underline">Remover logo</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        <form action="{{ route('placar.equipes.update', $equipe) }}" method="POST">
            @csrf
            @method('PUT')
            @include('placar.equipes.partials.form')

            <div class="mt-6 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold shadow-lg hover:bg-emerald-700 transition">Salvar Alterações</button>
            </div>
        </form>

        {{-- Times da equipe --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-6 border-b border-gray-50 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-800 dark:text-white">Times</h2>
                <a href="{{ route('placar.times.create') }}" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 hover:underline">+ Novo time</a>
            </div>
            @if($equipe->times->isEmpty())
                <div class="p-6 text-sm text-gray-500 dark:text-gray-400">Nenhum time cadastrado para esta equipe.</div>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($equipe->times as $time)
                    <li class="p-4 flex items-center justify-between">
                        <span class="text-sm text-gray-800 dark:text-gray-200">{{ $time->nomeExibicaoResolvido() }} <span class="text-xs text-gray-400">({{ $time->modalidade->nome }} - {{ $time->categoria }})</span></span>
                        <a href="{{ route('placar.times.show', $time) }}" class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">Ver</a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.equipes.destroy', $equipe) }}"
                  onsubmit="return confirm('Excluir a equipe \'{{ $equipe->nome }}\'?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition">
                    Excluir Equipe
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>
