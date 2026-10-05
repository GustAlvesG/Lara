<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Serviço') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <div class="mb-6 flex justify-end">
            <a href="{{ route('freelancer-services.bulk') }}" class="inline-flex items-center px-4 py-2 bg-surface text-ink rounded-xl font-bold shadow-card border border-line hover:bg-subtle transition">
                Registrar vários de uma vez
            </a>
        </div>

        @include('freelancer.partials.import-card', [
            'action' => route('freelancer-services.import'),
            'templateRoute' => route('freelancer-services.import.template'),
            'columns' => $importColumns,
            'hint' => 'O freelancer é localizado pelo CPF, que já deve estar cadastrado. A função precisa ser escrita com o mesmo nome usado no cadastro de Funções. Valor e horas pagas são calculados na importação.',
        ])

        @if(session('confirm_weekly_limit'))
            <div class="mb-6 bg-warn border border-warn/40 text-white dark:text-canvas px-6 py-4 rounded-2xl shadow-pop flex items-center gap-4">
                <div class="bg-white/20 p-2 rounded-full shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-extrabold text-lg leading-none">Atenção</p>
                    <p class="text-sm opacity-90 mt-1">{{ session('confirm_weekly_limit') }}</p>
                </div>
            </div>
        @endif

        <form action="{{ route('freelancer-services.store') }}" method="POST">
            @csrf
            @include('freelancer.services.partials.form', ['locked' => false])

            {{-- Acima do limite de 7 dias, quem libera é o coordenador do
                 Comercial — com o PIN dele, ou com um código enviado ao e-mail
                 dele quando não está presente.

                 O campo escondido mantém o bloco na tela mesmo quando o pedido
                 de código volta por erro de validação, que não reenvia o flash. --}}
            @if(session('confirm_weekly_limit') || old('weekly_limit_pending'))
                <div class="mt-6 bg-surface rounded-2xl shadow-pop border-2 border-warn/40 overflow-hidden">
                    <input type="hidden" name="weekly_limit_pending" value="1">
                    <div class="p-6 border-b border-line bg-warn-soft">
                        <h2 class="text-lg font-bold text-ink">Liberação do coordenador do setor Comercial</h2>
                        <p class="mt-1 text-sm text-ink-2">
                            Somente um coordenador do setor Comercial pode liberar este registro.
                            <b>Presencialmente</b>, ele informa a própria matrícula e o próprio PIN.
                            <b>À distância</b>, envie o código: ele vai para todos os coordenadores do setor
                            e qualquer um deles pode ditar o número — nesse caso a matrícula não é necessária.
                        </p>
                    </div>

                    <div class="p-6 space-y-6">
                        {{-- Mesmo formulário, outro destino: nada do que já foi
                             preenchido se perde ao pedir o código. Não pede
                             matrícula — o código vai para todos. --}}
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" formaction="{{ route('freelancer-services.weekly-limit-code') }}"
                                formnovalidate
                                class="inline-flex items-center px-4 py-2 bg-warn-soft text-warn rounded-xl font-bold border border-warn/40 hover:bg-warn/20 transition">
                                Nenhum coordenador presente? Enviar código por e-mail
                            </button>
                            <span class="text-xs text-ink-2">
                                Vai para todos os coordenadores do Comercial, com validade curta.
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-ink mb-1">Matrícula do coordenador</label>
                                <input type="text" name="coordinator_matricula" value="{{ old('coordinator_matricula') }}"
                                    inputmode="numeric" autocomplete="off"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-warn-soft focus:border-warn outline-none transition bg-surface text-ink">
                                <p class="mt-1 text-xs text-ink-3">
                                    Só para liberar com o PIN. Deixe em branco ao usar o código do e-mail.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-ink mb-1">PIN ou código <span class="text-danger">*</span></label>
                                <input type="password" name="coordinator_pin" inputmode="numeric" maxlength="6"
                                    pattern="[0-9]{6}" autocomplete="new-password" required placeholder="••••••"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-warn-soft focus:border-warn outline-none transition bg-surface text-ink tracking-[0.4em]">
                                <p class="mt-1 text-xs text-ink-3">
                                    6 dígitos: o PIN do coordenador, ou o código enviado por e-mail. Não fica guardado na tela.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('freelancer-services.index') }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                @if(session('confirm_weekly_limit') || old('weekly_limit_pending'))
                    <button type="submit" name="confirm_weekly_limit" value="1" class="px-6 py-3 bg-warn text-white dark:text-canvas rounded-xl font-bold shadow-card hover:bg-warn/90 transition">Liberar e registrar</button>
                @else
                    <button type="submit" class="px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition">Registrar</button>
                @endif
            </div>
        </form>
    </div>
</div>
</x-app-layout>
