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
<div data-row
     class="fade-in bg-white dark:bg-gray-800 rounded-2xl shadow-sm border {{ $expirado ? 'border-red-200 dark:border-red-900/50' : 'border-gray-100 dark:border-gray-700' }} overflow-hidden">

    <div class="w-full h-1 {{ $expirado ? 'bg-red-400' : 'bg-indigo-500' }}"></div>

    <div class="p-5">

        <div class="flex gap-4">

            <!-- Print da corrida -->
            <div class="shrink-0">
                @if($req->screenshot_url)
                    <a href="{{ $req->screenshot_url }}" target="_blank" rel="noopener" title="Abrir o print da corrida">
                        <img src="{{ $req->screenshot_url }}" alt="Print da corrida" loading="lazy"
                             class="w-24 h-24 rounded-xl object-cover border border-gray-200 dark:border-gray-600 hover:ring-2 hover:ring-indigo-400 transition">
                    </a>
                @else
                    <div class="w-24 h-24 rounded-xl border border-dashed border-gray-200 dark:border-gray-600 flex items-center justify-center text-[10px] text-gray-400 dark:text-gray-500 text-center px-2">
                        Sem print
                    </div>
                @endif
            </div>

            <!-- Identificação -->
            <div class="flex-1 min-w-0">
                <p class="text-lg font-black text-gray-900 dark:text-white truncate">{{ $req->requester_name ?: 'Sem nome informado' }}</p>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if($req->matricula)
                        <span>Matrícula/CPF <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $req->matricula }}</span></span>
                    @endif
                    @if($req->contact_phone)
                        <span class="font-mono">{{ $req->contact_phone }}</span>
                    @endif
                </div>

                @if($req->contact_name_whatsapp)
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">WhatsApp: {{ $req->contact_name_whatsapp }}</p>
                @endif

                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Local</span>
                    &nbsp;{{ $req->club_location ?: '—' }}
                </p>

                @if($req->member_validation)
                    <div class="mt-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold {{ $validationColors[$req->member_validation] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                            {{ $req->memberValidationLabel() }}
                        </span>
                        @if($req->member_validation_name)
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">{{ $req->member_validation_name }}</span>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Placa e validade -->
            <div class="shrink-0 text-right">
                <p class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-1">Placa</p>
                <p class="font-mono text-xl font-black tracking-wider text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700 rounded-lg px-3 py-1.5 inline-block">
                    {{ $req->vehicle_plate ?: '—' }}
                </p>
                <button type="button" data-plate-edit
                        onclick="corrigirPlaca({{ $req->id }}, {{ Js::from($req->vehicle_plate) }})"
                        class="block ml-auto mt-1.5 text-[11px] font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                    Corrigir placa
                </button>

                @if($req->expires_at)
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mt-3" data-expires="{{ $req->expires_at->toIso8601String() }}"></p>
                @endif
                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">
                    Pedido às {{ optional($req->completed_at ?? $req->created_at)->format('H:i') }}
                </p>
            </div>

        </div>

        <button type="button" onclick="liberar({{ $req->id }}, this)"
                class="w-full mt-4 px-5 py-2.5 {{ $expirado ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white rounded-xl font-black text-sm shadow-sm transition">
            {{ $expirado ? 'Liberar mesmo fora do prazo' : 'Liberar acesso' }}
        </button>

    </div>
</div>
