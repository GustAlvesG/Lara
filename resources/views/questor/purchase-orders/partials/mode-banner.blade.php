{{--
    A faixa que diz em que modo o módulo está.

    Existe porque a tela é idêntica nos dois casos, e a diferença entre "estou
    conferindo" e "acabei de autorizar uma compra no ERP" não pode depender de
    quem lembra da versão que está no ar.
--}}
@if($config['dry_run'])
    <div class="mb-6 rounded-2xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 px-6 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
            <div>
                <p class="font-extrabold text-amber-800 dark:text-amber-300">Modo simulação — nada é gravado no Questor</p>
                <p class="text-sm text-amber-700 dark:text-amber-200/80 mt-0.5">
                    Aprovar e reprovar aqui apenas montam o comando que <em>seria</em> enviado ao ERP e mostram o
                    antes/depois. A ordem continua exatamente como está no Questor.
                </p>
            </div>
        </div>
    </div>
@else
    <div class="mb-6 rounded-2xl border border-red-300 dark:border-red-800 bg-red-50 dark:bg-red-900/20 px-6 py-4">
        <p class="font-extrabold text-red-800 dark:text-red-300">Modo de gravação solicitado</p>
        <p class="text-sm text-red-700 dark:text-red-200/80 mt-0.5">
            QUESTOR_DRY_RUN está desligado, mas a escrita ainda não foi liberada nesta versão do módulo: as ações
            vão recusar em vez de gravar. Volte QUESTOR_DRY_RUN=true para simular.
        </p>
    </div>
@endif
