<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Externos Terceirizados - Novo') }}

        </h2>

    </x-slot>

    <x-slot name="css">
    <style>
        @keyframes btn-call-to-action {
            0%   { transform: scale(1);    box-shadow: 0 0 0 0   rgba(99,102,241,.7); }
            50%  { transform: scale(1.06); box-shadow: 0 0 0 10px rgba(99,102,241,0); }
            100% { transform: scale(1);    box-shadow: 0 0 0 0   rgba(99,102,241,0); }
        }
        .btn-attention {
            animation: btn-call-to-action 1s ease-in-out infinite;
        }
    </style>
    </x-slot>

    <div>
        <!-- Header -->
           
        <x-crud.form :formRoute="route('company.rules.store', $company)" :formMethod="'POST'" :hasImageSection="false">

            <x-slot name="header">
                <div class="my-4 flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-extrabold text-ink leading-tight">Nova Regra de Acesso</h1>
                        <p class="text-ink-2 font-medium">Configure as condições de entrada e permanência no local.</p>
                    </div>
                </div>
            </x-slot>

            <x-slot name="fields">

                <!-- Regra Rápida -->
                @if(!isset($rule))
                <div id="quick-rule-card" class="mb-6 p-5 bg-warn-soft border-2 border-warn/40 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-5 h-5 text-warn shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="font-extrabold text-warn text-sm">Regra Rápida</span>
                        </div>
                        <p class="text-xs text-warn">
                            Preenche automaticamente uma regra de <strong>inclusão</strong> válida somente para hoje,
                            <strong>{{ now()->format('d/m/Y') }}</strong>, até as <strong>23:59</strong>.
                        </p>
                    </div>
                    <button type="button" id="quick-rule-btn" onclick="applyQuickRule()"
                            class="shrink-0 flex items-center gap-2 px-5 py-2.5 bg-warn hover:bg-warn/90 text-white dark:text-canvas rounded-xl font-bold text-sm shadow-card transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Aplicar
                    </button>
                </div>
                @endif

                @include('companies.rules.partials.form')

            </x-slot>

        </x-crud.form>

    </div>

    
    <x-slot name="js">
        <script src="{{ asset('js/pagination.js') }}"></script>
        <script>
        function applyQuickRule() {
            const today = new Date();
            const pad   = n => String(n).padStart(2, '0');
            const dateStr = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;

            // Tipo: Inclusão
            const includeRadio = document.querySelector('input[name="type"][value="include"]');
            if (includeRadio) {
                includeRadio.checked = true;
                includeRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Datas: hoje → hoje
            const startDate = document.getElementById('start_date');
            const endDate   = document.getElementById('end_date');
            if (startDate) { startDate.value = dateStr; startDate.dispatchEvent(new Event('change', { bubbles: true })); }
            if (endDate)   { endDate.value   = dateStr; }

            // Horário: sem início, término 23:59
            const startTime = document.getElementById('start_time');
            const endTime   = document.getElementById('end_time');
            if (startTime) startTime.value = '';
            if (endTime)   endTime.value   = '23:59';

            // Dias da semana: somente hoje
            // DOM id: dom=1, seg=2, ter=3, qua=4, qui=5, sex=6, sab=7
            // JS getDay(): 0=Dom, 1=Seg, ..., 6=Sab → id = getDay() + 1
            const todayId = today.getDay() + 1;
            document.querySelectorAll('input[name="days[]"]').forEach(cb => {
                cb.checked = (parseInt(cb.value) === todayId);
            });

            // Feedback visual no botão
            const btn = document.getElementById('quick-rule-btn');
            if (btn) {
                btn.textContent = '✓ Aplicado';
                btn.classList.replace('bg-warn', 'bg-ok');
                btn.classList.replace('hover:bg-warn/90', 'hover:bg-ok');
                btn.disabled = true;
            }

            // Rola até o botão Finalizar e aplica animação após a rolagem terminar
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => submitBtn.classList.add('btn-attention'), 600);
            }
        }
        </script>
    </x-slot>
</x-app-layout>
