{{--
    O resultado da última decisão — simulada ou gravada.

    Mostra o SQL com os parâmetros porque é isso que se confere numa integração
    nova, e mostra `linhas_afetadas`, que é o que separa "aprovei" de "mandei e
    não pegou nada". Quando a gravação de fato aconteceu, o bloco de confirmação
    traz a ordem RELIDA do Questor: a prova do carimbo, não a suposição de que
    ele entrou porque o UPDATE não deu erro.
--}}
@php
    $aprovacao = $simulacao['acao'] === \App\Services\Questor\QuestorAuthorizationWriter::ACTION_APPROVE;
    $gravou = $simulacao['executado'] && $simulacao['linhas_afetadas'] > 0;
    $atencao = $simulacao['impedimentos'] !== [] || ($simulacao['executado'] && $simulacao['linhas_afetadas'] === 0);
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg border-2 {{ $atencao ? 'border-amber-300 dark:border-amber-700' : 'border-emerald-300 dark:border-emerald-700' }} overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="font-extrabold text-gray-900 dark:text-white">
                @if($simulacao['executado'])
                    {{ $aprovacao ? 'Autorização' : 'Reprovação' }} gravada no Questor — ordem #{{ $simulacao['cd_ordem_compra'] }}
                @else
                    Simulação de {{ $aprovacao ? 'autorização' : 'reprovação' }} — ordem #{{ $simulacao['cd_ordem_compra'] }}
                @endif
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                @if($gravou)
                    O comando abaixo foi executado no ERP.
                @elseif($simulacao['executado'])
                    O comando foi enviado mas não alterou nenhuma linha — nada mudou no ERP.
                @else
                    Nada foi gravado no Questor. Abaixo, o que <em>seria</em> enviado.
                @endif
            </p>
        </div>
        <span class="px-3 py-1.5 rounded-lg text-xs font-bold tabular-nums
            {{ $simulacao['linhas_afetadas'] > 0
                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' }}">
            {{ $simulacao['linhas_afetadas'] }} linha(s)
            {{ $simulacao['executado'] ? 'afetadas' : 'seriam afetadas' }}
        </span>
    </div>

    <div class="p-6 space-y-6">

        @if($temImpedimento)
            <div class="rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-4">
                <p class="font-bold text-amber-800 dark:text-amber-300 text-sm">
                    O que impediria a gravação real
                </p>
                <ul class="mt-2 space-y-1 text-sm text-amber-700 dark:text-amber-200/80 list-disc list-inside">
                    @foreach($simulacao['impedimentos'] as $impedimento)
                        <li>{{ $impedimento }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($simulacao['ressalvas'] !== [])
            <div class="rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 p-4">
                <p class="font-bold text-gray-700 dark:text-gray-200 text-sm">Ressalvas</p>
                <ul class="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300 list-disc list-inside">
                    @foreach($simulacao['ressalvas'] as $ressalva)
                        <li>{{ $ressalva }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ANTES / DEPOIS --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Como está hoje</p>
                <dl class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($simulacao['antes'] as $campo => $valor)
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $campo }}</dt>
                            <dd class="text-right text-gray-900 dark:text-white">{{ $valor ?? 'NULL' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                    {{ $simulacao['executado'] ? 'O que foi gravado' : 'Como ficaria' }}
                </p>
                <dl class="text-sm divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach($simulacao['depois'] as $campo => $valor)
                        <div class="flex justify-between gap-4 py-1.5">
                            <dt class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $campo }}</dt>
                            <dd class="text-right font-semibold text-emerald-700 dark:text-emerald-400">{{ $valor ?? 'NULL' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="text-xs text-gray-400 mt-2">
                    Os campos não listados ficam intactos — inclusive DT_ATUALIZACAO, que a tela nativa também não
                    toca ao autorizar.
                </p>
            </div>
        </div>

        {{-- CONFIRMAÇÃO: a ordem relida do Questor depois da gravação --}}
        @if($simulacao['confirmacao'])
            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 p-4">
                <p class="font-bold text-emerald-800 dark:text-emerald-300 text-sm">
                    Confirmação — a ordem relida do Questor depois da gravação
                </p>
                <dl class="mt-2 text-sm grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                    @foreach($simulacao['confirmacao'] as $campo => $valor)
                        <div class="flex justify-between gap-4 py-1">
                            <dt class="font-mono text-xs text-emerald-700/70 dark:text-emerald-300/70">{{ $campo }}</dt>
                            <dd class="text-right text-emerald-900 dark:text-emerald-100">{{ $valor ?? 'NULL' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        {{-- SQL --}}
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                {{ $simulacao['executado'] ? 'Comando executado' : 'Comando que seria enviado' }}
            </p>
            <pre class="rounded-xl bg-gray-900 text-gray-100 text-xs p-4 overflow-x-auto"><code>{{ $simulacao['sql'] }}</code></pre>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mt-4 mb-2">Parâmetros (na ordem dos "?")</p>
            <pre class="rounded-xl bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 text-xs p-4 overflow-x-auto"><code>{{ json_encode($simulacao['bindings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
        </div>

        {{-- USUÁRIO TÉCNICO --}}
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                {{ $simulacao['executado'] ? 'Usuário técnico que assinou' : 'Usuário técnico que assinaria' }}
            </p>
            @if($simulacao['usuario_tecnico'])
                <p class="text-sm text-gray-700 dark:text-gray-200">
                    <strong>#{{ $simulacao['usuario_tecnico']->CD_CODUSUARIO }}</strong>
                    {{ $simulacao['usuario_tecnico']->DS_LOGIN }} ({{ $simulacao['usuario_tecnico']->DS_USUARIO }}) ·
                    {{ (int) $simulacao['usuario_tecnico']->X_ATIVO ? 'ativo' : 'inativo' }} ·
                    autoriza: {{ (int) $simulacao['usuario_tecnico']->X_AUTORIZA_ORDEM_COMPRA ? 'sim' : 'não' }} ·
                    reprova: {{ (int) $simulacao['usuario_tecnico']->X_REPROVA_ORDEM_COMPRA ? 'sim' : 'não' }}
                </p>
            @else
                <p class="text-sm text-amber-600 dark:text-amber-400">
                    Nenhum usuário técnico configurado — o Questor mostraria a autorização em nome de quem?
                    Defina QUESTOR_USUARIO_TECNICO antes de liberar a gravação.
                </p>
            @endif
            <p class="text-xs text-gray-400 mt-2">
                O Questor só tem lugar para um autorizador, e é sempre este. Quem clicou fica no histórico de
                decisões da Lara, abaixo.
            </p>
        </div>

    </div>
</div>
