<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validar {{ $service->isAmendment() ? 'termo' : 'contrato' }} #{{ $service->id }} — {{ $service->freelancer->name }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    {{--
        Página própria, como a de impressão, e não dentro do layout do painel: o
        coordenador valida o DOCUMENTO, e ele aparece aqui exatamente como é
        impresso — mesmo parcial, mesmo CSS. A validação fica ao pé dele e só é
        liberada quando a rolagem chega ao fim.
    --}}
    <style>
        :root{ --paper:#fffefb; --ink:#241f1a; --line:#c9bfb4; --muted:#6b6156; --red:#c8102e; --red2:#a30711;
            --serif: ui-serif, Georgia, "Times New Roman", serif; --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        *{ box-sizing:border-box; }
        html,body{ margin:0; padding:0; }
        body{ background:#e9e6e2; font-family:var(--sans); color:var(--ink); }

        .toolbar{ position:sticky; top:0; z-index:10; display:flex; align-items:center; gap:12px;
            background:#1f1819; color:#fff; padding:12px 18px; }
        .toolbar .sp{ flex:1; }
        .toolbar a{ font-family:inherit; font-size:14px; font-weight:700; border-radius:10px; padding:10px 16px;
            text-decoration:none; background:transparent; color:#d9cfd0; border:1px solid rgba(255,255,255,.25); }
        .toolbar .title{ font-size:14px; font-weight:600; color:#cfc7c8; }
        .toolbar .progress{ font-size:12px; color:#cfc7c8; }

        .wrap{ padding:24px 16px 60px; display:flex; flex-direction:column; align-items:center; gap:18px; }
        .flash{ width:100%; max-width:820px; border-radius:12px; padding:14px 18px; font-size:14px; font-weight:600; }
        .flash.error{ background:#fde8e8; color:#8a0f12; border:1px solid #f5b5b5; }
        .flash.info{ background:#eef2ff; color:#3730a3; border:1px solid #c7d2fe; }
        .flash.warn{ background:#fef3c7; color:#92400e; border:1px solid #fcd34d; }

        @include('freelancer.services.partials.contract-document-styles')

        .end-marker{ height:1px; }

        .panel{ width:100%; max-width:820px; background:#fff; border-radius:14px; padding:22px 24px;
            box-shadow:0 10px 30px -18px rgba(0,0,0,.4); border:2px solid #e5dcd3; }
        .panel h2{ margin:0 0 6px; font-size:18px; }
        .panel p{ margin:0 0 12px; font-size:14px; color:#4b433c; line-height:1.5; }
        .panel.locked{ opacity:.55; }
        .panel .row{ display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
        .panel label{ display:block; font-size:13px; font-weight:700; margin-bottom:6px; }
        .panel input[type=password]{ font-family:ui-monospace, monospace; font-size:22px; letter-spacing:8px;
            width:190px; padding:10px 14px; border:1px solid #d6ccc2; border-radius:10px; }
        .panel button{ font-family:inherit; font-size:15px; font-weight:800; cursor:pointer; border:0; border-radius:12px;
            padding:13px 22px; background:#A00001; color:#fff; }
        .panel button:disabled{ background:#b9aea4; cursor:not-allowed; }
        .panel .hint{ font-size:12px; color:#8a7f75; margin-top:10px; }
        .panel .err{ color:#b91c1c; font-size:12px; margin-top:6px; }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('freelancer-validation.index') }}">← Fila de validação</a>
        <span class="title">
            {{ $service->isAmendment() ? 'Termo' : 'Contrato' }} #{{ $service->id }} · {{ $service->freelancer->name }}
            · {{ $service->contractVersionLabel() }}
        </span>
        <span class="sp"></span>
        <span class="progress">{{ $remaining }} outro(s) aguardando validação</span>
    </div>

    <div class="wrap">
        @if(session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif
        @if(isset($errors) && $errors->any())
            <div class="flash error">{{ $errors->first() }}</div>
        @endif

        @if($service->kindNote())
            <div class="flash info">{{ $service->kindLabel() }}: {{ $service->kindNote() }}</div>
        @endif

        @include('freelancer.services.partials.contract-document', ['service' => $service])

        {{-- O fim do documento. Quando ele entra na tela, a validação é liberada. --}}
        <div class="end-marker" id="endOfDocument"></div>

        @if($blockReason)
            <div class="panel">
                <h2>Validação indisponível</h2>
                <p>{{ $blockReason }}</p>
                @if($service->coordinator_signed_at)
                    <p class="hint">
                        Validado em {{ $service->coordinator_signed_at->format('d/m/Y H:i') }}
                        @if($service->coordinatorSignedBy) por {{ $service->coordinatorSignedBy->name }} @endif.
                    </p>
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('freelancer-validation.store', $service) }}" class="panel locked" id="validationPanel">
                @csrf
                <input type="hidden" name="read_token" value="{{ $token }}">
                <input type="hidden" name="read_to_end" value="0" id="readToEnd">

                <h2>Validar este {{ $service->isAmendment() ? 'termo' : 'contrato' }}</h2>
                <p>
                    Ao validar, você confirma, pela coordenação, que o serviço de <b>{{ $service->freelancer->name }}</b>
                    em <b>{{ $service->start_date?->format('d/m/Y') }}</b> aconteceu como descrito neste documento.
                    Validado, ele fica disponível para a montagem de lote. A validação não entra no documento:
                    pelo CONTRATANTE assina a diretoria, na aprovação do lote.
                </p>

                @if(!$hasPin)
                    <p class="err">Você ainda não tem PIN definido — peça para defini-lo na tela de Usuários.</p>
                @endif

                <fieldset id="validationFields" disabled style="border:0;padding:0;margin:0;">
                    <div class="row">
                        <div>
                            <label for="pin">Seu PIN (6 dígitos)</label>
                            <input type="password" name="pin" id="pin" inputmode="numeric" pattern="\d{6}" maxlength="6"
                                   autocomplete="off" required>
                        </div>
                        <button type="submit" id="validateButton">Validar contrato</button>
                    </div>
                </fieldset>
                <p class="hint" id="readHint">Role o documento até o fim para liberar a validação.</p>
            </form>
        @endif
    </div>

    @unless($blockReason)
    <script>
        (function () {
            var marker = document.getElementById('endOfDocument');
            var panel = document.getElementById('validationPanel');
            var fields = document.getElementById('validationFields');
            var readToEnd = document.getElementById('readToEnd');
            var hint = document.getElementById('readHint');

            // Libera uma vez só: voltar a rolar para cima não trava de novo — o
            // documento já foi percorrido até o fim.
            function unlock() {
                fields.disabled = false;
                readToEnd.value = '1';
                panel.classList.remove('locked');
                hint.textContent = 'Documento lido até o fim. Digite seu PIN para validar.';
                document.getElementById('pin').focus({ preventScroll: true });
            }

            if (!('IntersectionObserver' in window)) {
                // Navegador sem suporte: cai na conferência pela rolagem.
                window.addEventListener('scroll', function onScroll() {
                    if (marker.getBoundingClientRect().top <= window.innerHeight) {
                        window.removeEventListener('scroll', onScroll);
                        unlock();
                    }
                });
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                if (entries.some(function (e) { return e.isIntersecting; })) {
                    observer.disconnect();
                    unlock();
                }
            });

            // Começa a observar só depois do primeiro gesto de rolagem: um
            // documento curto numa tela grande já mostraria o fim ao abrir, e a
            // regra é percorrê-lo, não apenas carregá-lo.
            window.addEventListener('scroll', function start() {
                window.removeEventListener('scroll', start);
                observer.observe(marker);
            }, { passive: true });

            // O caso oposto: o documento inteiro cabe na tela e não há o que
            // rolar — sem esta saída o botão nunca seria liberado. Conferido no
            // `load`, depois das imagens do cabeçalho, que mudam a altura.
            window.addEventListener('load', function () {
                if (document.documentElement.scrollHeight <= window.innerHeight + 2) {
                    observer.disconnect();
                    unlock();
                }
            });
        })();
    </script>
    @endunless
</body>
</html>
