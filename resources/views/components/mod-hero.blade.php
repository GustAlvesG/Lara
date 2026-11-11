@props([
    // Cor da faixa: ext | freela | pen | buy | card | score | tix | rec | ha | chat | mail | staff
    'variant' => 'pen',
    // O objeto à direita (lg+). Sem informar, vem o do variant:
    // badge | punch | signature | receipt | card | score | bracket | ticket | rec | bulb | chat | mail
    'motif' => null,
    // Texto da faixa do crachá (motif badge).
    'badgeLabel' => 'Externo',
    'eyebrow' => null,
    'title',
    // [['label' => 'Em assinatura', 'value' => 3, 'href' => '...', 'tone' => 'warn|danger|ok|null', 'current' => bool], ...]
    // value nulo = atalho sem número (seta).
    'stats' => [],
    'label' => null,
])

{{--
    Faixa de identidade dos módulos (11/11/2026, na pegada do SIV e de
    Reservas): Externos, Freelancers, Assinaturas, Contratos e, desde a
    segunda leva, Compras, Carteirinhas, Placar, Torneios, Ingressos, RePET,
    Home Assistant, Bot WhatsApp, HAL, Funcionários e Disparo de E-mails.
    Escura nos dois temas, texto sempre branco (app.css: mod-band). À
    direita, na tela larga, o objeto do módulo — decorativo, sem dado.

    Os números são os que a tela já tem; com `href`, viram atalho. O slot
    leva texto curto.
--}}
@php
    $motif ??= [
        'ext' => 'badge', 'staff' => 'badge', 'freela' => 'punch', 'pen' => 'signature', 'buy' => 'receipt',
        'card' => 'card', 'score' => 'score', 'tix' => 'ticket', 'rec' => 'rec', 'ha' => 'bulb',
        'chat' => 'chat', 'mail' => 'mail',
    ][$variant] ?? 'signature';

    $tones = [
        'warn' => 'rgb(251 191 36)',
        'danger' => 'rgb(252 165 165)',
        'ok' => 'rgb(134 239 172)',
    ];
@endphp

<section {{ $attributes->merge(['class' => "mod-band mod-band--{$variant} rounded-[28px] shadow-card"]) }} aria-label="{{ $label ?? $title }}">
    <div class="auth-enter grid grid-cols-1 items-center gap-8 p-6 sm:p-9 lg:grid-cols-[minmax(0,1fr)_auto] lg:gap-12">
        <div class="flex min-w-0 flex-col gap-6">
            <div>
                @if ($eyebrow)
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-white/60">{{ $eyebrow }}</p>
                @endif
                <h2 class="mt-2 font-display text-[26px] font-semibold leading-tight tracking-tight sm:text-[34px]">{{ $title }}</h2>
                @if (trim($slot) !== '')
                    <div class="mt-2 max-w-2xl text-[15px] leading-relaxed text-white/75">{{ $slot }}</div>
                @endif
            </div>

            @if ($stats)
                <div class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/10 sm:flex sm:flex-wrap">
                    @foreach ($stats as $stat)
                        @php
                            $tone = $tones[$stat['tone'] ?? ''] ?? null;
                            $tag = ! empty($stat['href']) ? 'a' : 'div';
                        @endphp
                        <{{ $tag }} @if ($tag === 'a') href="{{ $stat['href'] }}" @if (! empty($stat['current'])) aria-current="page" @endif @endif
                            class="group flex min-w-[9rem] flex-1 flex-col-reverse gap-1.5 bg-black/15 px-4 py-4 text-white no-underline transition sm:px-5 {{ $tag === 'a' ? 'hover:bg-white/10 focus:outline-none focus-visible:ring-4 focus-visible:ring-inset focus-visible:ring-white/30' : '' }} {{ ! empty($stat['current']) ? 'bg-white/15' : '' }}">
                            <span class="text-xs font-semibold leading-snug text-white/70 group-hover:text-white">
                                {{ $stat['label'] }}@if ($tag === 'a')<span aria-hidden="true"> →</span>@endif
                            </span>
                            @if (($stat['value'] ?? null) === null)
                                {{-- Atalho sem número (a tela não conta aquela situação). --}}
                                <span class="grid h-[30px] w-[30px] place-items-center rounded-full bg-white/15 transition group-hover:translate-x-0.5 motion-reduce:transform-none" aria-hidden="true">
                                    <x-icon name="arrow-right" class="h-4 w-4" />
                                </span>
                            @else
                                <span class="font-mono text-[30px] font-semibold leading-none" @if ($tone) style="color: {{ $tone }}" @endif>{{ $stat['value'] }}</span>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- O objeto do módulo (só na tela larga, decorativo). --}}
        <div class="hidden pr-4 lg:block" aria-hidden="true">
            @switch ($motif)
                @case('badge')
                    <div class="mt-16 ext-badge">
                        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full" style="background-color: rgb(var(--a-externos)); color: rgb(var(--a-externos-ink))">
                            <x-icon name="user" class="h-7 w-7" />
                        </div>
                        <div class="mx-auto mt-3 h-2 w-24 rounded-full bg-[#1b2030]/80"></div>
                        <div class="mx-auto mt-2 h-2 w-16 rounded-full bg-[#1b2030]/25"></div>
                        <div class="mt-4 rounded-lg py-1.5 text-center text-[11px] font-black uppercase tracking-[0.2em] text-[#1b2030]" style="background-color: rgb(var(--ext-badge))">{{ $badgeLabel }}</div>
                    </div>
                    @break

                @case('punch')
                    <div class="w-56 rounded-2xl bg-[#fbf6ee] p-4 text-[#3a1d0b] shadow-pop">
                        <div class="flex items-center justify-between border-b-2 border-[#3a1d0b]/80 pb-2">
                            <span class="font-display text-sm font-semibold">Cartão de ponto</span>
                            <x-icon name="clock" class="h-4 w-4" />
                        </div>
                        @foreach (['Entrada', 'Saída', 'Entrada', 'Saída'] as $row)
                            <div class="flex items-center justify-between border-b border-dashed border-[#3a1d0b]/25 py-2 text-[11px] font-bold uppercase tracking-[0.12em]">
                                <span>{{ $row }}</span>
                                <span class="h-2.5 w-14 rounded-sm" style="background-color: {{ $loop->index < 2 ? 'rgb(var(--freela-deep))' : 'rgb(58 29 11 / .12)' }}"></span>
                            </div>
                        @endforeach
                    </div>
                    @break

                @case('receipt')
                    {{-- Compras: o mapa de cotação como um cupom, com o melhor preço marcado. --}}
                    <div class="mod-receipt w-56 bg-[#fbfaf3] px-5 pb-8 pt-5 text-[#2c2618] shadow-pop">
                        <div class="text-center font-display text-sm font-semibold uppercase tracking-[0.14em]">Cotação</div>
                        <div class="my-3 border-t-2 border-dashed border-[#2c2618]/30"></div>
                        @foreach ([[28, 12, false], [20, 14, true], [24, 10, false]] as [$w1, $w2, $best])
                            <div class="flex items-center justify-between py-1.5">
                                <span class="h-2 rounded-full bg-[#2c2618]/40" style="width: {{ $w1 * 4 }}px"></span>
                                <span class="h-2 rounded-full {{ $best ? 'bg-[rgb(22_128_74)]' : 'bg-[#2c2618]/25' }}" style="width: {{ $w2 * 3 }}px"></span>
                            </div>
                        @endforeach
                        <div class="my-3 border-t-2 border-dashed border-[#2c2618]/30"></div>
                        <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-[0.14em]">
                            <span>Melhor preço</span>
                            <x-icon name="check" class="h-4 w-4 text-[rgb(22_128_74)]" />
                        </div>
                    </div>
                    @break

                @case('card')
                    {{-- Carteirinhas: o cartão do sócio, com o logo do clube. --}}
                    <div class="relative h-[150px] w-[240px] overflow-hidden rounded-2xl bg-gradient-to-br from-[#f7f4ef] to-[#e8e1d6] p-4 text-[#1d2433] shadow-pop">
                        <div class="absolute inset-x-0 top-0 h-2" style="background-color: rgb(var(--plate-band))"></div>
                        <div class="flex items-start justify-between">
                            <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-9 object-contain" loading="lazy">
                            <span class="h-7 w-9 rounded-md bg-gradient-to-br from-[#d8b45a] to-[#a8842f]"></span>
                        </div>
                        <div class="mt-5 flex items-end gap-3">
                            <span class="grid h-12 w-10 place-items-center rounded-md bg-[#1d2433]/10"><x-icon name="user" class="h-6 w-6 text-[#1d2433]/50" /></span>
                            <span class="flex-1">
                                <span class="block h-2 w-28 rounded-full bg-[#1d2433]/75"></span>
                                <span class="mt-2 block h-1.5 w-20 rounded-full bg-[#1d2433]/25"></span>
                                <span class="mt-1.5 block h-1.5 w-16 rounded-full bg-[#1d2433]/25"></span>
                            </span>
                        </div>
                    </div>
                    @break

                @case('score')
                    {{-- Placar: o painel de LED. --}}
                    <div class="w-64 rounded-2xl bg-[#0c0f0a] p-4 shadow-pop ring-1 ring-white/10">
                        <div class="flex items-center justify-between text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">
                            <span>Casa</span><span>Período</span><span>Visitante</span>
                        </div>
                        <div class="mt-2 flex items-center justify-between font-mono text-[44px] font-semibold leading-none text-[rgb(255_196_64)] [text-shadow:0_0_14px_rgb(255_196_64/.55)]">
                            <span>00</span><span class="text-xl text-white/40">1º</span><span>00</span>
                        </div>
                        <div class="mt-3 text-center font-mono text-sm text-[rgb(134_239_172)] [text-shadow:0_0_10px_rgb(134_239_172/.6)]">00:00</div>
                    </div>
                    @break

                @case('bracket')
                    {{-- Torneios: a chave, de oito a um. --}}
                    <svg class="h-40 w-64 text-white" viewBox="0 0 256 160" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        @foreach ([10, 40, 90, 120] as $y)
                            <rect x="2" y="{{ $y }}" width="56" height="20" rx="6" class="fill-white/10" stroke-opacity=".5" />
                        @endforeach
                        <path d="M58 20h20v30H58M78 35h22M58 100h20v30H58M78 115h22" stroke-opacity=".5" />
                        <rect x="100" y="25" width="56" height="20" rx="6" class="fill-white/15" stroke-opacity=".7" />
                        <rect x="100" y="105" width="56" height="20" rx="6" class="fill-white/15" stroke-opacity=".7" />
                        <path d="M156 35h20v80h-20M176 75h22" stroke-opacity=".7" />
                        <rect x="198" y="63" width="56" height="24" rx="8" fill="rgb(255 196 64)" stroke="none" />
                        <path d="M218 75l5 5 10-10" stroke="#0c0f0a" stroke-width="2.5" />
                    </svg>
                    @break

                @case('ticket')
                    {{-- Ingressos: o ingresso com o canhoto picotado. --}}
                    <div class="mod-ticket flex h-[132px] w-64 overflow-hidden rounded-2xl bg-[#fbf4ee] text-[rgb(var(--tix-deep))] shadow-pop">
                        <div class="flex flex-1 flex-col justify-between p-4">
                            <span class="font-display text-sm font-semibold uppercase tracking-[0.16em]">Ingresso</span>
                            <span>
                                <span class="block h-2 w-28 rounded-full bg-current opacity-80"></span>
                                <span class="mt-2 block h-1.5 w-20 rounded-full bg-current opacity-30"></span>
                            </span>
                        </div>
                        <div class="flex w-16 flex-col items-center justify-center gap-[3px] border-l-2 border-dashed border-[rgb(var(--tix-deep)/.3)] px-2">
                            @foreach ([3, 1, 2, 1, 3, 2, 1, 2] as $bar)
                                <span class="block w-10 bg-current" style="height: {{ $bar }}px"></span>
                            @endforeach
                        </div>
                    </div>
                    @break

                @case('rec')
                    {{-- RePET: o quadro do vídeo, gravando. --}}
                    <div class="relative h-[144px] w-64 overflow-hidden rounded-2xl bg-[#0b0b0e] shadow-pop ring-1 ring-white/10">
                        <div class="absolute left-3 top-3 flex items-center gap-1.5 text-[11px] font-black tracking-[0.18em] text-white">
                            <span class="mod-rec-dot h-2.5 w-2.5 rounded-full bg-[rgb(239_68_68)]"></span> REC
                        </div>
                        <div class="absolute right-3 top-3 font-mono text-[11px] text-white/60">00:00:00</div>
                        <div class="absolute inset-0 grid place-items-center">
                            <span class="grid h-12 w-12 place-items-center rounded-full bg-white/15 ring-1 ring-white/25">
                                <svg class="ml-1 h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </div>
                        <div class="absolute inset-x-3 bottom-3 h-1 rounded-full bg-white/15"><div class="h-full w-2/5 rounded-full bg-[rgb(239_68_68)]"></div></div>
                    </div>
                    @break

                @case('bulb')
                    {{-- Home Assistant: a lâmpada acesa sobre o interruptor. --}}
                    <div class="flex flex-col items-center gap-3">
                        <span class="grid h-24 w-24 place-items-center rounded-full bg-[rgb(255_196_64/.15)] shadow-[0_0_60px_rgb(255_196_64/.45)]">
                            <x-icon name="bulb" class="h-12 w-12 text-[rgb(255_196_64)]" />
                        </span>
                        <span class="flex h-16 w-10 items-start justify-center rounded-xl bg-[#f4f1ea] p-1.5 shadow-pop">
                            <span class="h-6 w-full rounded-md bg-[rgb(255_196_64)]"></span>
                        </span>
                    </div>
                    @break

                @case('chat')
                    {{-- Bot WhatsApp e HAL: a conversa. --}}
                    <div class="flex w-60 flex-col gap-2.5">
                        <span class="w-40 self-start rounded-2xl rounded-bl-md bg-white/90 px-4 py-3 shadow-pop">
                            <span class="block h-2 w-24 rounded-full bg-[#0a3a33]/60"></span>
                            <span class="mt-2 block h-2 w-16 rounded-full bg-[#0a3a33]/25"></span>
                        </span>
                        <span class="w-36 self-end rounded-2xl rounded-br-md bg-[rgb(134_239_172)] px-4 py-3 shadow-pop">
                            <span class="block h-2 w-20 rounded-full bg-[#0a3a33]/60"></span>
                        </span>
                        <span class="flex w-20 items-center gap-1.5 self-start rounded-2xl rounded-bl-md bg-white/90 px-4 py-3 shadow-pop">
                            <span class="h-2 w-2 rounded-full bg-[#0a3a33]/50"></span><span class="h-2 w-2 rounded-full bg-[#0a3a33]/35"></span><span class="h-2 w-2 rounded-full bg-[#0a3a33]/20"></span>
                        </span>
                    </div>
                    @break

                @case('mail')
                    {{-- Disparo de E-mails: o envelope selado. --}}
                    <svg class="h-36 w-56" viewBox="0 0 224 144">
                        <rect x="4" y="8" width="216" height="132" rx="14" fill="#fbfaf6" />
                        <path d="M8 18l104 70 104-70" fill="none" stroke="rgb(var(--mail-deep))" stroke-opacity=".35" stroke-width="3" stroke-linejoin="round" />
                        <rect x="168" y="20" width="38" height="44" rx="4" fill="none" stroke="rgb(var(--plate-band))" stroke-width="2.5" stroke-dasharray="4 3" />
                        <circle cx="187" cy="42" r="10" fill="rgb(var(--plate-band))" fill-opacity=".85" />
                        <rect x="24" y="104" width="80" height="7" rx="3.5" fill="rgb(var(--mail-deep))" fill-opacity=".55" />
                        <rect x="24" y="118" width="54" height="6" rx="3" fill="rgb(var(--mail-deep))" fill-opacity=".25" />
                    </svg>
                    @break

                @default
                    <div class="w-64 rounded-2xl bg-[#fbfaf6] p-5 text-[rgb(var(--pen-ink))] shadow-pop">
                        <div class="h-2 w-40 rounded-full bg-[rgb(var(--pen-ink))]/80"></div>
                        <div class="mt-2.5 h-1.5 w-52 rounded-full bg-[rgb(var(--pen-ink))]/15"></div>
                        <div class="mt-1.5 h-1.5 w-48 rounded-full bg-[rgb(var(--pen-ink))]/15"></div>
                        <div class="mt-1.5 h-1.5 w-36 rounded-full bg-[rgb(var(--pen-ink))]/15"></div>
                        <svg class="pen-stroke mt-4 h-14 w-full" viewBox="0 0 220 56">
                            <path d="M6 38c14-18 24-30 30-26 7 5-12 30-6 33 6 3 18-28 26-25 7 3-3 22 4 22 9 0 15-22 24-20 8 2 0 17 8 17 10 0 14-16 22-14 6 2 2 12 9 12 12 0 30-8 52-11 10-1 22 0 33 2"/>
                        </svg>
                        <div class="border-t-2 border-[rgb(var(--pen-ink))]/70 pt-1.5 text-[10px] font-bold uppercase tracking-[0.18em] opacity-70">Assinatura</div>
                    </div>
            @endswitch
        </div>
    </div>
</section>
