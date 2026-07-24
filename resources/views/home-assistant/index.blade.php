<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Home Assistant
        </h2>
    </x-slot>

    <x-slot name="css">
        <style>[x-cloak]{ display: none !important; }</style>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8 space-y-8">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="p-4 bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-300 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif
            @if($errors->any())
                <div class="p-4 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-300 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ══════════════ Contatores ══════════════ --}}
            <section class="space-y-4">
                <div class="p-4 sm:p-5 bg-white dark:bg-gray-800 shadow sm:rounded-xl flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Contatores</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $contactors->count() }} cadastrado(s) — controle imediato e status</span>
                    </div>
                    <button onclick="document.getElementById('modal-new-contactor').classList.remove('hidden')"
                        class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-700 hover:bg-gray-900 text-white text-sm font-medium rounded-lg transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Novo Contator
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @forelse($contactors as $contactor)
                        @include('home-assistant.partials.contactor-card', ['contactor' => $contactor])
                    @empty
                        <div class="col-span-full p-8 bg-white dark:bg-gray-800 shadow sm:rounded-xl text-center text-gray-500 dark:text-gray-400">
                            Nenhum contator cadastrado. Adicione o primeiro acima.
                        </div>
                    @endforelse
                </div>
            </section>

            {{-- ══════════════ Agendamentos ══════════════ --}}
            <section class="space-y-4" x-data="{ showArchived: false }">
                <div class="p-4 sm:p-5 bg-white dark:bg-gray-800 shadow sm:rounded-xl flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Agendamentos</h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Regras com múltiplos dias, horários e locais — sobrescrevem o padrão por prioridade</span>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($archivedOverrides->isNotEmpty())
                            <button @click="showArchived = !showArchived"
                                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>
                                <span x-text="showArchived ? 'Ocultar arquivados' : 'Pausados / expirados'"></span>
                                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-200">{{ $archivedOverrides->count() }}</span>
                            </button>
                        @endif
                        <button onclick="document.getElementById('modal-new-schedule').classList.remove('hidden')"
                            @disabled($contactors->isEmpty())
                            class="inline-flex items-center px-4 py-2 bg-red-800 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Novo Agendamento
                        </button>
                    </div>
                </div>

                {{-- Ativos --}}
                @if($activeOverrides->isEmpty() && $archivedOverrides->isEmpty())
                    <div class="p-8 bg-white dark:bg-gray-800 shadow sm:rounded-xl text-center text-gray-500 dark:text-gray-400">
                        Nenhum agendamento criado. Use "Novo Agendamento" para definir dias, horários e locais.
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @forelse($activeOverrides as $override)
                            @include('home-assistant.partials.schedule-card', ['override' => $override])
                        @empty
                            <div class="col-span-full p-6 bg-white dark:bg-gray-800 shadow sm:rounded-xl text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum agendamento ativo no momento.
                            </div>
                        @endforelse
                    </div>
                @endif

                {{-- Pausados / expirados (filtráveis) --}}
                @if($archivedOverrides->isNotEmpty())
                    <div x-show="showArchived" x-cloak x-transition class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Pausados / Expirados</span>
                            <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($archivedOverrides as $override)
                                @include('home-assistant.partials.schedule-card', ['override' => $override])
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>

    {{-- Modal: novo contator --}}
    <div id="modal-new-contactor" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md">
            <div class="flex items-center justify-between p-5 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Novo Contator</h3>
                <button onclick="document.getElementById('modal-new-contactor').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form method="POST" action="{{ route('home-assistant.store') }}" class="p-5 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                    <input type="text" name="name" required placeholder="Ex: Quadra 1 - Luzes"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity ID (Home Assistant)</label>
                    <input type="text" name="entity_id" required placeholder="Ex: switch.quadra_1"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-800 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Deve corresponder exatamente ao entity_id no Home Assistant.</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('modal-new-contactor').classList.add('hidden')"
                        class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancelar
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm bg-red-800 hover:bg-red-700 text-white rounded-lg font-medium">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: novo agendamento --}}
    @include('home-assistant.partials.schedule-form', [
        'modalId'    => 'modal-new-schedule',
        'action'     => route('home-assistant.overrides.store'),
        'method'     => 'POST',
        'title'      => 'Novo agendamento',
        'override'   => null,
        'contactors' => $contactors,
        'weekdays'   => $weekdays,
    ])

    <x-slot name="js">
        <script>
            // Componente Alpine do formulário de agendamento
            function scheduleForm(config) {
                return {
                    mode: config.mode || 'schedule_override',
                    windows: (config.windows && config.windows.length)
                        ? config.windows
                        : [{ turn_on_at: '08:00', turn_off_at: '18:00', state: 'on' }],
                    addWindow() { this.windows.push({ turn_on_at: '08:00', turn_off_at: '18:00', state: 'on' }); },
                    removeWindow(i) { this.windows.splice(i, 1); },
                };
            }

            // Fecha qualquer modal ao clicar no backdrop
            document.querySelectorAll('[id^="modal-"]').forEach(function (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === this) this.classList.add('hidden');
                });
            });
        </script>
    </x-slot>
</x-app-layout>
