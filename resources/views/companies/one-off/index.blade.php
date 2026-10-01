<x-app-layout>

    <div class="max-w-full mx-auto py-8 px-4">

        <!-- Header -->
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="{{ route('company.access.monitor') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-extrabold text-ink">Liberação Pontual</h1>
                    <p class="text-sm text-ink-2">Uma entrada, no dia, sem vínculo com empresa. Para o caso extraordinário.</p>
                </div>
            </div>
            <a href="{{ route('company.one-off.create') }}"
               class="px-4 py-2 bg-grena text-white rounded-lg font-bold text-sm shadow-card hover:bg-grena-hover transition">
                Nova Liberação
            </a>
        </div>

        @include('partials.alerts')

        <!-- Stats do dia -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ink">{{ $stats['total'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Liberações no dia</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-warn">{{ $stats['available'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Aguardando entrada</p>
            </div>
            <div class="bg-surface rounded-2xl shadow-card border border-line p-5">
                <p class="text-2xl font-black text-ok">{{ $stats['used'] }}</p>
                <p class="text-xs text-ink-2 font-medium">Utilizadas</p>
            </div>
        </div>

        <!-- Filtro -->
        <form method="GET" action="{{ route('company.one-off.index') }}"
              class="bg-surface rounded-2xl shadow-card border border-line p-5 mb-6 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-bold text-ink-3 uppercase tracking-wider mb-1.5">Dia</label>
                <input type="date" name="date" value="{{ $date->toDateString() }}"
                       class="px-3 py-2.5 border border-line rounded-xl text-sm outline-none focus:ring-2 focus:ring-grena-tint bg-surface text-ink dark:[color-scheme:dark]">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-grena text-white rounded-xl font-bold text-sm hover:bg-grena-hover transition shadow-card">
                Filtrar
            </button>
            @unless($date->isToday())
                <a href="{{ route('company.one-off.index') }}" class="px-4 py-2.5 bg-surface border border-line text-ink-2 rounded-xl font-bold text-sm hover:bg-subtle transition">
                    Hoje
                </a>
            @endunless
        </form>

        @if($accesses->isNotEmpty())
            {{-- A lista do dia vem inteira: a busca filtra aqui mesmo. --}}
            <x-search-bar mode="client" target="#liberacoes" placeholder="Buscar pessoa, documento, motivo ou quem autorizou" class="mb-4" />
        @endif

        <!-- Tabela -->
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            @if($accesses->isEmpty())
                <div class="py-16 text-center">
                    <p class="text-ink-3 font-medium">Nenhuma liberação pontual em {{ $date->format('d/m/Y') }}.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b border-line bg-subtle">
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Criada</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Pessoa</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Motivo</th>
                            <th class="px-5 py-3.5 text-left text-[11px] font-black text-ink-3 uppercase tracking-wider">Autorizada por</th>
                            <th class="px-5 py-3.5 text-center text-[11px] font-black text-ink-3 uppercase tracking-wider">Status</th>
                            <th class="px-5 py-3.5"></th>
                        </tr>
                    </thead>
                    <tbody id="liberacoes" class="divide-y divide-line">
                        @foreach($accesses as $access)
                            @php
                                $status = $access->status();
                                $badge = [
                                    'available' => 'bg-warn-soft text-warn',
                                    'used'      => 'bg-ok-soft text-ok',
                                    'canceled'  => 'bg-subtle text-ink-2',
                                    'expired'   => 'bg-danger-soft text-danger',
                                ][$status];
                            @endphp
                            <tr data-search="{{ $status }}" class="hover:bg-subtle transition align-top">
                                <td class="px-5 py-3.5 whitespace-nowrap font-semibold text-ink">
                                    {{ $access->created_at->format('H:i') }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        @if($access->imageUrl())
                                            <img src="{{ $access->imageUrl() }}" class="w-9 h-9 rounded-full object-cover shrink-0" alt="">
                                        @else
                                            <div class="w-9 h-9 rounded-full bg-warn-soft text-warn flex items-center justify-center text-sm font-black shrink-0">
                                                {{ mb_strtoupper(mb_substr($access->name, 0, 1)) }}
                                            </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-ink">{{ $access->name }}</p>
                                            <p class="font-mono text-xs text-ink-3">{{ $access->formattedCpf() }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3.5 text-ink-2 max-w-md whitespace-pre-line">{{ $access->reason }}</td>
                                <td class="px-5 py-3.5 text-ink-2">{{ $access->creator?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-black uppercase {{ $badge }}">{{ $access->statusLabel() }}</span>
                                    @if($access->used_at)
                                        <p class="text-xs text-ink-3 mt-1">entrou às {{ $access->used_at->format('H:i') }}</p>
                                    @elseif($access->canceled_at)
                                        <p class="text-xs text-ink-3 mt-1">às {{ $access->canceled_at->format('H:i') }}{{ $access->canceler ? ' por ' . $access->canceler->name : '' }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if($status === 'available')
                                        <form method="POST" action="{{ route('company.one-off.cancel', $access) }}"
                                              onsubmit="return confirm('Cancelar a liberação de {{ addslashes($access->name) }}? A portaria deixa de encontrá-la.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="px-3 py-1.5 bg-surface border border-danger/40 text-danger rounded-lg text-xs font-bold hover:bg-danger-soft transition">
                                                Cancelar
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

    </div>

</x-app-layout>
