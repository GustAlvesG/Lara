{{--
    A faixa que diz em que modo o módulo está.

    Existe porque a tela é idêntica nos dois casos, e a diferença entre "estou
    conferindo" e "acabei de autorizar uma compra no ERP" não pode depender de
    quem lembra da configuração que está no ar.
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
    <div class="mb-6 rounded-2xl border-2 border-red-400 dark:border-red-700 bg-red-50 dark:bg-red-900/20 px-6 py-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div>
                <p class="font-extrabold text-red-800 dark:text-red-300">Gravação ativa — as decisões vão para o Questor de produção</p>
                <p class="text-sm text-red-700 dark:text-red-200/80 mt-0.5">
                    Aprovar aqui carimba a ordem no ERP de verdade, em nome do usuário técnico
                    @if($usuarioTecnico ?? null)
                        <strong>{{ $usuarioTecnico->DS_LOGIN }}</strong> (#{{ $usuarioTecnico->CD_CODUSUARIO }}).
                    @else
                        configurado.
                    @endif
                    Quem clicou fica registrado na trilha da Lara.
                    @unless($config['reprovacao_liberada'])
                        A <strong>reprovação</strong> segue em simulação até o teste ao vivo dela ser feito.
                    @endunless
                </p>
            </div>
        </div>
    </div>
@endif
