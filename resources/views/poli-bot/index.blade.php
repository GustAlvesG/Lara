<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Bot WhatsApp') }}</h2>
    </x-slot>

    @php
        $modos = [
            'off' => ['Desligado', 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200', 'O bot da Poli atende. A Lara só escuta o fluxo do Uber.'],
            'shadow' => ['Modo sombra', 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300', 'O bot da Poli atende. A Lara registra o que responderia, sem enviar nada.'],
            'on' => ['No ar', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300', 'A Lara responde os associados. O bot da Poli deve estar desligado.'],
        ];
        [$modoRotulo, $modoCor, $modoTexto] = $modos[$modo] ?? $modos['off'];
    @endphp

    <div class="py-10 bg-gray-50 dark:bg-gray-900 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-xl border border-green-200 bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
                    @foreach ($errors->all() as $erro) <p>{{ $erro }}</p> @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold text-gray-900 dark:text-white">Fluxos de conversa</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">O que o bot pergunta, como confere cada resposta e o que faz depois.</p>
                </div>
                <a href="{{ route('poli-bot.flows.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700">
                    + Novo fluxo
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 p-5 flex flex-wrap items-center gap-4">
                <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $modoCor }}">{{ $modoRotulo }}</span>
                <p class="text-sm text-gray-600 dark:text-gray-300 flex-1 min-w-[16rem]">{{ $modoTexto }}</p>
                <p class="text-xs text-gray-400">Muda no servidor: <code>POLI_BOT_MODE</code></p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach ([
                    'conversas' => 'Conversas',
                    'recebidas' => 'Mensagens recebidas',
                    'respostas' => $modo === 'on' ? 'Respostas enviadas' : 'Respostas (simuladas)',
                    'transbordos' => 'Passadas a atendente',
                    'pedidos_uber' => 'Pedidos de carro',
                    'falhas' => 'Falhas de envio',
                ] as $chave => $rotulo)
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-100 dark:border-gray-700 p-4">
                        <p class="text-2xl font-extrabold {{ $chave === 'falhas' && $numeros[$chave] > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' }}">{{ number_format($numeros[$chave], 0, ',', '.') }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $rotulo }}</p>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 -mt-3">Últimos 7 dias.</p>

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-xs text-left text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-4 py-3">Fluxo</th>
                                <th class="px-4 py-3">Começa com</th>
                                <th class="px-4 py-3">Passos</th>
                                <th class="px-4 py-3">Situação</th>
                                <th class="px-4 py-3">Última alteração</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($fluxos as $f)
                                @php $m = $f['model']; @endphp
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('poli-bot.flows.edit', $m) }}" class="font-semibold text-gray-900 dark:text-white hover:underline">{{ $m->name }}</a>
                                        <p class="text-xs text-gray-400 font-mono">{{ $m->slug }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-xs">{{ $f['gatilho'] }}</td>
                                    <td class="px-4 py-3">{{ $f['passos'] }}</td>
                                    <td class="px-4 py-3">
                                        @if ($f['erros'] !== [])
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-800" title="{{ implode(' · ', $f['erros']) }}">Com problemas</span>
                                        @elseif ($m->active)
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800">Ativo</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-gray-100 text-gray-600">Rascunho</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500">
                                        @if ($f['versao'])
                                            {{ $f['versao']->created_at?->format('d/m/Y H:i') }}
                                            @if ($f['versao']->user_name) · {{ $f['versao']->user_name }} @endif
                                        @else
                                            {{ $m->updated_at?->format('d/m/Y H:i') }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('poli-bot.flows.edit', $m) }}" class="text-indigo-600 hover:underline text-xs font-semibold">Editar</a>
                                        <form method="POST" action="{{ route('poli-bot.flows.toggle', $m) }}" class="inline ml-3">
                                            @csrf
                                            <button class="text-xs font-semibold {{ $m->active ? 'text-amber-600' : 'text-green-700' }} hover:underline">
                                                {{ $m->active ? 'Desativar' : 'Ativar' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                                        Nenhum fluxo ainda. Crie um em <strong>Novo fluxo</strong>, ou instale os fluxos padrão no servidor com
                                        <code>php artisan poli:bot-fluxos --instalar</code>.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
