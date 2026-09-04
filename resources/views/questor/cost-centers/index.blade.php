{{--
    Relação centro de custo → diretores.

    O que se cadastra aqui é a SUGESTÃO que aparece para o gerente no nível 2 —
    não a decisão. A tela diz isso em voz alta, porque um cadastro que na
    verdade define alçada, e é lido como se definisse, vira briga depois.

    Ordenado por valor parado, e não por código: o centro de custo que segura
    R$ 400 mil precisa de dono antes do que segura R$ 300.
--}}
@php
    $brl = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $semDiretor = $centros->filter(fn($c) => empty($relacao[(int) $c->CD_CENTRO_CUSTO] ?? []))->count();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Centros de Custo') }}
        </h2>
    </x-slot>

<div class="py-12 bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">Aprovadores por Centro de Custo</h1>
            <p class="text-gray-500 dark:text-gray-400 font-medium">
                Quem entra como sugestão no último nível da aprovação de compras. A Gerência confirma ou troca
                na hora de aprovar — inclusive para as ordens sem centro de custo, que não têm sugestão nenhuma.
            </p>
        </div>

        @include('partials.alerts')

        @if($erro)
            <div class="mb-6 bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-red-200 dark:border-red-900 p-6">
                <p class="font-extrabold text-red-700 dark:text-red-400">O Questor não respondeu</p>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $erro }}</p>
            </div>
        @endif

        @if($diretores->isEmpty())
            <div class="mb-6 rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-6 py-4">
                <p class="font-extrabold text-amber-800 dark:text-amber-300">O setor Diretoria está vazio</p>
                <p class="text-sm text-amber-700 dark:text-amber-200/80 mt-0.5">
                    Sem ninguém vinculado ao setor, não há quem escolher aqui e nenhuma ordem chega ao último
                    nível. Vincule os diretores em <a href="{{ route('sectors.index') }}" class="underline font-semibold">Setores</a>,
                    marcando o presidente como coordenador.
                </p>
            </div>
        @elseif($semDiretor > 0)
            <div class="mb-6 rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-6 py-4">
                <p class="font-extrabold text-amber-800 dark:text-amber-300">
                    {{ $semDiretor }} centro(s) de custo sem diretor
                </p>
                <p class="text-sm text-amber-700 dark:text-amber-200/80 mt-0.5">
                    Ordens desses centros de custo chegam ao gerente sem sugestão — ele terá de escolher o
                    aprovador na mão, ordem a ordem.
                </p>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-extrabold text-gray-900 dark:text-white">
                    {{ $centros->count() }} centro(s) de custo na fila
                </h2>
                <span class="text-xs text-gray-400 dark:text-gray-500">
                    Ordenados pelo valor parado em ordens pendentes
                </span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($centros as $centro)
                    @php
                        $codigo = (int) $centro->CD_CENTRO_CUSTO;
                        $atuais = $relacao[$codigo] ?? [];
                    @endphp
                    <form method="POST" action="{{ route('questor.cost-centers.update', $codigo) }}"
                          class="px-6 py-5 grid grid-cols-1 lg:grid-cols-[18rem_1fr_auto] gap-4 lg:gap-6 items-start">
                        @csrf
                        @method('PUT')

                        <div class="min-w-0">
                            <p class="font-mono text-sm font-bold text-gray-900 dark:text-white">{{ $codigo }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 truncate">
                                {{ $centro->DS_CENTRO_CUSTO ?: 'sem descrição no Questor' }}
                            </p>
                            <p class="text-xs text-gray-400 tabular-nums mt-0.5">
                                {{ $centro->ORDENS }} ordem(ns) · {{ $brl($centro->VALOR) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @forelse($diretores as $diretor)
                                {{-- Checkbox estilizado como chip: a lista é curta e a
                                     leitura importa mais que a economia de espaço. --}}
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="diretores[]" value="{{ $diretor->id }}"
                                           class="peer sr-only" @checked(in_array($diretor->id, $atuais, true))>
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium border transition
                                                 border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400
                                                 peer-checked:bg-[#A00001] peer-checked:text-white peer-checked:border-[#A00001]
                                                 peer-focus-visible:ring-2 peer-focus-visible:ring-offset-1 peer-focus-visible:ring-[#A00001]">
                                        {{ $diretor->name }}
                                    </span>
                                </label>
                            @empty
                                <span class="text-sm text-gray-400">Nenhum diretor cadastrado.</span>
                            @endforelse
                        </div>

                        <button type="submit" @disabled($diretores->isEmpty())
                                class="px-4 py-2 rounded-xl bg-gray-900 dark:bg-gray-700 text-white text-sm font-bold
                                       disabled:opacity-40 disabled:cursor-not-allowed transition shrink-0">
                            Salvar
                        </button>
                    </form>
                @empty
                    <p class="px-6 py-12 text-center text-gray-400">
                        {{ $erro ? 'Não foi possível carregar os centros de custo.' : 'Nenhum centro de custo nas ordens pendentes.' }}
                    </p>
                @endforelse
            </div>
        </div>

        <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">
            Desmarcar um diretor não apaga o vínculo — ele fica inativo, para as ordens que ele já decidiu
            continuarem fazendo sentido quando alguém for ler o histórico.
        </p>

    </div>
</div>
</x-app-layout>
