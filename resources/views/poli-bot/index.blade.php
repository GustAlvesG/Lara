{{--
    Bot do WhatsApp: em que modo ele está, os números da semana e os fluxos de
    conversa. A lista de fluxos vem inteira: a busca filtra na página.
--}}
@php
    $modos = [
        'off' => ['Desligado', 'off', 'O bot da Poli atende. A Lara só escuta o fluxo do Uber.'],
        'shadow' => ['Modo sombra', 'warn', 'O bot da Poli atende. A Lara registra o que responderia, sem enviar nada.'],
        'on' => ['No ar', 'ok', 'A Lara responde os associados. O bot da Poli deve estar desligado.'],
    ];
    [$modoRotulo, $modoTipo, $modoTexto] = $modos[$modo] ?? $modos['off'];
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Bot WhatsApp">
            O que o bot pergunta, como confere cada resposta e o que faz depois.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('poli-bot.flows.create') }}"><x-icon name="plus" /> Novo fluxo</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft p-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $erro) <p>{{ $erro }}</p> @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 rounded-card bg-surface p-4 shadow-card">
            <x-pill :kind="$modoTipo">{{ $modoRotulo }}</x-pill>
            <p class="min-w-[16rem] flex-1 text-sm text-ink-2">{{ $modoTexto }}</p>
            <p class="text-xs text-ink-3">Muda no servidor: <code class="font-mono">POLI_BOT_MODE</code></p>
        </div>

        <div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([
                    'conversas' => 'Conversas',
                    'recebidas' => 'Mensagens recebidas',
                    'respostas' => $modo === 'on' ? 'Respostas enviadas' : 'Respostas (simuladas)',
                    'transbordos' => 'Passadas a atendente',
                    'pedidos_uber' => 'Pedidos de carro',
                    'falhas' => 'Falhas de envio',
                ] as $chave => $rotulo)
                    <div class="rounded-card bg-surface p-4 shadow-card">
                        <p class="font-mono text-2xl font-semibold {{ $chave === 'falhas' && $numeros[$chave] > 0 ? 'text-danger' : 'text-ink' }}">{{ number_format($numeros[$chave], 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs font-bold text-ink-2">{{ $rotulo }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-ink-3">Últimos 7 dias.</p>
        </div>

        @if (count($fluxos) === 0)
            <x-empty-state icon="chat">
                Nenhum fluxo ainda. Crie um em <strong>Novo fluxo</strong>, ou instale os fluxos padrão no servidor com
                <code class="font-mono text-xs">php artisan poli:bot-fluxos --instalar</code>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#fluxos" placeholder="Buscar fluxo, gatilho ou situação" />

            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Fluxo</th>
                                <th class="{{ $th }}">Começa com</th>
                                <th class="{{ $th }}">Passos</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Última alteração</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody id="fluxos" class="divide-y divide-line">
                            @foreach ($fluxos as $f)
                                @php $m = $f['model']; @endphp
                                <tr data-search="" class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('poli-bot.flows.edit', $m) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $m->name }}</a>
                                        <p class="font-mono text-xs text-ink-3">{{ $m->slug }}</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink-2">{{ $f['gatilho'] }}</td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $f['passos'] }}</td>
                                    <td class="px-5 py-3.5">
                                        @if ($f['erros'] !== [])
                                            <span title="{{ implode(' · ', $f['erros']) }}"><x-pill kind="danger">Com problemas</x-pill></span>
                                        @elseif ($m->active)
                                            <x-pill kind="ok">Ativo</x-pill>
                                        @else
                                            <x-pill kind="off">Rascunho</x-pill>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink-2">
                                        @if ($f['versao'])
                                            <span class="font-mono">{{ $f['versao']->created_at?->format('d/m/Y H:i') }}</span>
                                            @if ($f['versao']->user_name) · {{ $f['versao']->user_name }} @endif
                                        @else
                                            <span class="font-mono">{{ $m->updated_at?->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-secondary-button-a size="sm" href="{{ route('poli-bot.flows.edit', $m) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                                            <form method="POST" action="{{ route('poli-bot.flows.toggle', $m) }}">
                                                @csrf
                                                <x-secondary-button size="sm" type="submit">{{ $m->active ? 'Desativar' : 'Ativar' }}</x-secondary-button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>
