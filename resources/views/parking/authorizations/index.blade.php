@php
    $columns = ['plate' => 'Placa', 'name' => 'Nome', 'expiration_date' => 'Validade'];
    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Placas Diretoria">
            Placas liberadas na cancela sem depender de cadastro de sócio.

            <x-slot:actions>
                <x-primary-button-a href="{{ route('parking-authorizations.create') }}"><x-icon name="plus" /> Nova placa</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        {{-- Push manual --}}
        <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" style="{{ \App\View\AreaColor::style('portaria') }}">
                    <x-icon name="arrow-right" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="font-display text-lg font-semibold tracking-tight text-ink">Push manual</h3>
                    <p class="text-sm text-ink-2">Abre a cancela diretamente, sem depender da leitura da placa. Tempo selecionado + 5 segundos de segurança.</p>
                    <p id="manual-push-feedback" class="mt-2 hidden text-sm" aria-live="polite"></p>
                </div>
            </div>
            <div class="flex items-end gap-3">
                <div>
                    <label for="manual-push-seconds" class="mb-1 block text-xs font-bold text-ink-2">Tempo aberto (seg)</label>
                    <x-text-input type="number" id="manual-push-seconds" min="0.1" step="0.1" value="2" class="w-28 font-mono" />
                </div>
                <x-primary-button type="button" id="manual-push-btn"><x-icon name="arrow-right" /> Abrir cancela</x-primary-button>
            </div>
        </section>

        {{-- Busca no servidor: a lista é paginada. A ordenação escolhida
             viaja junto (o x-search-bar mantém os outros parâmetros). --}}
        <x-search-bar placeholder="Buscar por placa ou nome" />

        @if ($authorizations->isEmpty())
            <x-empty-state icon="car">
                @if ($search !== '')
                    Nenhuma placa encontrada para “{{ $search }}”.
                @else
                    Nenhuma placa cadastrada.
                    <a href="{{ route('parking-authorizations.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar a primeira</a>.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                @foreach ($columns as $column => $label)
                                    @php
                                        $active = $sort === $column;
                                        $nextDirection = $active && $direction === 'asc' ? 'desc' : 'asc';
                                    @endphp
                                    <th class="{{ $th }}" @if ($active) aria-sort="{{ $direction === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                        <a href="{{ route('parking-authorizations.index', ['q' => $search, 'sort' => $column, 'direction' => $nextDirection]) }}"
                                           class="inline-flex items-center gap-1 no-underline hover:text-ink {{ $active ? 'text-grena-ink' : 'text-ink-3' }}">
                                            {{ $label }}
                                            @if ($active)
                                                <x-icon name="chevron-down" class="h-3.5 w-3.5 {{ $direction === 'asc' ? 'rotate-180' : '' }}" />
                                            @endif
                                        </a>
                                    </th>
                                @endforeach
                                <th class="{{ $th }}">Status</th>
                                <th class="{{ $th }} text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($authorizations as $item)
                                @php $expired = $item->expiration_date->lt(\Carbon\Carbon::today()); @endphp
                                <tr class="border-b border-line transition last:border-0 hover:bg-subtle">
                                    <td class="{{ $td }}"><x-plate :plate="$item->plate" size="sm" /></td>
                                    <td class="{{ $td }} font-semibold text-ink">{{ $item->name }}</td>
                                    <td class="{{ $td }} whitespace-nowrap font-mono text-ink-2">{{ $item->expiration_date->format('d/m/Y') }}</td>
                                    <td class="{{ $td }}">
                                        <x-pill :kind="$expired ? 'danger' : 'ok'">{{ $expired ? 'Expirada' : 'Válida' }}</x-pill>
                                    </td>
                                    <td class="{{ $td }} whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('parking-authorizations.edit', $item) }}" title="Editar" aria-label="Editar placa {{ $item->plate }}"
                                               class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-subtle hover:text-ink">
                                                <x-icon name="pencil" />
                                            </a>
                                            <form method="POST" action="{{ route('parking-authorizations.destroy', $item) }}" onsubmit="return confirm('Confirma a remoção desta placa?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" title="Remover" aria-label="Remover placa {{ $item->plate }}"
                                                        class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>{{ $authorizations->links() }}</div>
        @endif
    </x-page>

    <x-slot name="js">
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const btn = document.getElementById('manual-push-btn');
                const secondsInput = document.getElementById('manual-push-seconds');
                const feedback = document.getElementById('manual-push-feedback');

                function say(text, tone) {
                    feedback.textContent = text;
                    feedback.className = 'mt-2 text-sm font-semibold ' + tone;
                }

                btn.addEventListener('click', function () {
                    const seconds = parseFloat(secondsInput.value);

                    if (!seconds || seconds <= 0) {
                        say('Informe um tempo válido em segundos.', 'text-danger');
                        return;
                    }

                    btn.disabled = true;
                    say('Enviando...', 'text-ink-3');

                    // O dispositivo não responde a preflight CORS, então usamos
                    // modo 'no-cors' com Content-Type simples (sem application/json)
                    // para evitar o OPTIONS e garantir que o POST realmente saia.
                    // Como consequência, a resposta fica opaca e não dá para
                    // confirmar programaticamente se o comando teve sucesso.
                    fetch('http://192.168.100.96:8017/trigger/lara-push', {
                        method: 'POST',
                        mode: 'no-cors',
                        headers: { 'Content-Type': 'text/plain' },
                        body: JSON.stringify({
                            pin: 17,
                            active_high: true,
                            pulse_seconds: seconds
                        })
                    })
                        .then(function () {
                            say('Comando enviado ao dispositivo.', 'text-ok');
                        })
                        .catch(function (error) {
                            console.error(error);
                            say('Falha ao acionar a cancela. Verifique a conexão com o dispositivo.', 'text-danger');
                        })
                        .finally(function () {
                            btn.disabled = false;
                        });
                });
            });
        </script>
    </x-slot>
</x-app-layout>
