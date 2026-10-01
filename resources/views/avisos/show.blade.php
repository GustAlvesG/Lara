@php
    $privacyLabels = [
        'pessoa' => 'Pessoal',
        'setor' => 'Setor',
        'publico' => 'Público',
        'grupo' => 'Grupo',
    ];
    $th = 'px-5 py-2.5 text-left text-xs font-bold text-ink-3';
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-slot name="css">
        <style>
            .aviso-content b, .aviso-content strong { font-weight: 700; }
            .aviso-content i, .aviso-content em { font-style: italic; }
            .aviso-content u { text-decoration: underline; }
            .aviso-content a { color: rgb(var(--grena-ink)); text-decoration: underline; }
        </style>
    </x-slot>

    <x-page narrow>
        <x-page-title :title="$aviso->title" :back="route('avisos.index')">
            Publicado por {{ $aviso->creator->name ?? '—' }} em {{ $aviso->created_at->format('d/m/Y \à\s H:i') }}

            @auth
                <x-slot:actions>
                    <x-secondary-button-a href="{{ route('avisos.edit', $aviso) }}">
                        <x-icon name="pencil" /> Editar
                    </x-secondary-button-a>
                </x-slot:actions>
            @endauth
        </x-page-title>

        <article class="overflow-hidden rounded-card bg-surface shadow-card">
            @if ($aviso->image)
                <x-media :src="asset('images/avisos/' . $aviso->image)" :alt="$aviso->title" area="info" icon="bell" ratio="short" />
            @endif

            <div class="flex flex-col gap-4 p-5 sm:p-6">
                <div class="flex flex-wrap gap-1.5">
                    <x-pill kind="info" :icon="false">{{ $privacyLabels[$aviso->privacy] ?? $privacyLabels['setor'] }}</x-pill>
                    @if ($aviso->mandatory)
                        <x-pill kind="warn" :icon="false"><x-icon name="eye" class="h-3.5 w-3.5" /> Leitura obrigatória</x-pill>
                    @endif

                    @if ($aviso->isExpired())
                        <x-pill kind="off">Expirado em {{ $aviso->expires_at->format('d/m/Y') }}</x-pill>
                    @elseif ($aviso->expires_at)
                        <x-pill :kind="$aviso->expiresSoon() ? 'warn' : 'ok'">Expira em {{ $aviso->expires_at->format('d/m/Y') }}</x-pill>
                    @endif
                </div>

                @if ($aviso->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($aviso->tags as $tag)
                            <a href="{{ route('avisos.index', ['q' => $tag->name]) }}"
                                class="rounded-full bg-area-info px-2.5 py-1 text-xs font-semibold text-area-info-ink no-underline hover:underline">#{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($aviso->content)
                    <div class="aviso-content max-w-none text-[15px] leading-relaxed text-ink">
                        {!! $aviso->content !!}
                    </div>
                @endif

                @if ($aviso->lembretes->isNotEmpty())
                    <div class="border-t border-line pt-4">
                        <p class="mb-2 text-xs font-bold uppercase tracking-[0.08em] text-ink-3">Lembretes</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($aviso->lembretes as $lembrete)
                                <x-pill :kind="$lembrete->sent ? 'off' : 'info'" :icon="false">
                                    <x-icon name="bell" class="h-3 w-3" />
                                    <span class="font-mono">{{ $lembrete->remind_at->format('d/m/Y H:i') }}</span>
                                    @if ($lembrete->sent)
                                        <span class="opacity-70">(enviado)</span>
                                    @endif
                                </x-pill>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </article>

        {{-- Quem já confirmou a leitura (avisos de leitura obrigatória) --}}
        @if ($aviso->mandatory)
            @php $acknowledgements = $acknowledgements ?? collect(); @endphp
            <section class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="flex items-center justify-between gap-3 px-5 py-4">
                    <h2 class="flex items-center gap-2 text-sm font-bold text-ink">
                        <x-icon name="check" class="h-4 w-4 text-ok" /> Ciência registrada
                    </h2>
                    <span class="text-sm text-ink-2">{{ $acknowledgements->count() }} {{ $acknowledgements->count() === 1 ? 'pessoa' : 'pessoas' }}</span>
                </div>
                @if ($acknowledgements->isEmpty())
                    <p class="border-t border-line px-5 py-4 text-sm text-ink-3">Ninguém confirmou a leitura ainda.</p>
                @else
                    <ul class="divide-y divide-line border-t border-line">
                        @foreach ($acknowledgements as $ack)
                            <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                                <span class="font-semibold text-ink">{{ $ack->user->name ?? 'Usuário removido' }}</span>
                                <span class="font-mono text-xs text-ink-2">{{ $ack->acknowledged_at?->format('d/m/Y H:i') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif

        {{-- Quem já leu --}}
        @if ($viewHistory->isNotEmpty())
            <section class="overflow-hidden rounded-card bg-surface shadow-card" x-data="{ open: false }">
                <button type="button" @click="open = !open" :aria-expanded="open.toString()"
                    class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left text-sm font-bold text-ink transition hover:bg-subtle">
                    <span class="flex items-center gap-2">
                        <x-icon name="eye" class="h-4 w-4 text-ink-3" />
                        Histórico de acessos
                        <span class="rounded-full bg-subtle px-2 py-0.5 font-mono text-xs text-ink-2">
                            {{ $viewHistory->count() }} {{ $viewHistory->count() === 1 ? 'pessoa' : 'pessoas' }}
                        </span>
                    </span>
                    <x-icon name="chevron-down" class="h-4 w-4 text-ink-3 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                </button>

                <div x-show="open" x-cloak class="overflow-x-auto border-t border-line">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Pessoa</th>
                                <th class="{{ $th }}">Último acesso</th>
                                <th class="{{ $th }} text-right">Acessos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($viewHistory as $entry)
                                <tr class="border-b border-line last:border-0">
                                    <td class="px-5 py-3 font-semibold text-ink">{{ $entry['user']->name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-ink-2">
                                        <span class="font-mono">{{ $entry['last_view']->format('d/m/Y H:i') }}</span>
                                        <span class="ml-1 text-xs text-ink-3">({{ $entry['last_view']->diffForHumans() }})</span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-mono font-semibold text-ink">{{ $entry['count'] }}×</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </x-page>
</x-app-layout>
