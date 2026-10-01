@php
    /**
     * Uma linha da fila da portaria. Tudo o que o porteiro precisa para
     * decidir cabe no card — nome, print, local, placa e conferência do sócio
     * — porque a decisão é visual: ele compara o que está na tela com o carro
     * e o motorista parados na frente dele.
     */
    $expirado = $expirado ?? false;
@endphp

{{-- data-row é o gancho do botão "Liberar", que marca o card depois da resposta. --}}
<div data-search="" data-row
     class="fade-in bg-surface rounded-2xl shadow-card border {{ $expirado ? 'border-danger/40' : 'border-line' }} overflow-hidden">

    <div class="w-full h-1 {{ $expirado ? 'bg-danger' : 'bg-grena' }}"></div>

    <div class="p-5">

        <div class="flex gap-4">

            <!-- Print da corrida -->
            <div class="shrink-0">
                @if($req->screenshot_url)
                    <a href="{{ $req->screenshot_url }}" target="_blank" rel="noopener" title="Abrir o print da corrida">
                        <img src="{{ $req->screenshot_url }}" alt="Print da corrida" loading="lazy"
                             class="w-24 h-24 rounded-xl object-cover border border-line hover:ring-2 hover:ring-grena-tint transition">
                    </a>
                @else
                    <div class="w-24 h-24 rounded-xl border border-dashed border-line flex items-center justify-center text-[10px] text-ink-3 text-center px-2">
                        Sem print
                    </div>
                @endif
            </div>

            <!-- Identificação -->
            <div class="flex-1 min-w-0">
                <p class="text-lg font-black text-ink truncate">{{ $req->requester_name ?: 'Sem nome informado' }}</p>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-ink-2">
                    @if($req->matricula)
                        <span>Matrícula/CPF <span class="font-semibold text-ink">{{ $req->matricula }}</span></span>
                    @endif
                    @if($req->contact_phone)
                        <span class="font-mono">{{ $req->contact_phone }}</span>
                    @endif
                </div>

                @if($req->contact_name_whatsapp)
                    <p class="text-xs text-ink-3 mt-0.5 truncate">WhatsApp: {{ $req->contact_name_whatsapp }}</p>
                @endif

                <p class="text-sm text-ink-2 mt-2">
                    <span class="text-xs font-bold text-ink-3 uppercase tracking-wider">Local</span>
                    &nbsp;{{ $req->club_location ?: '—' }}
                </p>

                @if($req->member_validation)
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ $validationColors[$req->member_validation] ?? 'bg-subtle text-ink-2' }}">
                            {{ $req->memberValidationLabel() }}
                        </span>
                        @if($req->member_validation_name)
                            <span class="text-xs text-ink-3 ml-1">{{ $req->member_validation_name }}</span>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Placa e validade -->
            <div class="shrink-0 text-right">
                <p class="text-xs font-bold text-ink-3 uppercase tracking-wider mb-1">Placa</p>
                <p class="font-mono text-xl font-black tracking-wider text-ink bg-subtle rounded-lg px-3 py-1.5 inline-block">
                    {{ $req->vehicle_plate ?: '—' }}
                </p>
                <button type="button" data-plate-edit
                        onclick="corrigirPlaca({{ $req->id }}, {{ Js::from($req->vehicle_plate) }})"
                        class="block ml-auto mt-1.5 text-[11px] font-bold text-grena-ink hover:underline">
                    Corrigir placa
                </button>

                @if($req->expires_at)
                    <p class="text-xs font-semibold text-ink-2 mt-3" data-expires="{{ $req->expires_at->toIso8601String() }}"></p>
                @endif
                <p class="text-[11px] text-ink-3 mt-0.5">
                    Pedido às {{ optional($req->completed_at ?? $req->created_at)->format('H:i') }}
                </p>
            </div>

        </div>

        <button type="button" onclick="liberar({{ $req->id }}, this)"
                class="w-full mt-4 px-5 py-2.5 {{ $expirado ? 'bg-warn hover:bg-warn/90' : 'bg-grena hover:bg-grena-hover' }} text-white dark:text-canvas rounded-xl font-black text-sm shadow-card transition">
            {{ $expirado ? 'Liberar mesmo fora do prazo' : 'Liberar acesso' }}
        </button>

    </div>
</div>
