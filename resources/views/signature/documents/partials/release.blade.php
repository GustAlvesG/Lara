@php
    /**
     * A liberação por QR Code, na tela do atendente.
     *
     * O QR é desenhado NO NAVEGADOR, a partir do token que a rota devolve. O
     * token existe só nessa resposta: não é gravado, não vira arquivo e não
     * passa por lugar nenhum onde alguém possa recuperá-lo depois.
     *
     * A biblioteca vem por CDN, como o webcam.js e o chart.js que este projeto
     * já carrega assim.
     */
    /*
     | Só há quem liberar quando o documento está congelado e aguardando: um
     | rascunho ainda tem signatário pendente, e mostrar o botão ali ofereceria
     | uma ação que o servidor recusa.
     */
    $proximo = $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE
        ? $document->nextSigner()
        : null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 p-6"
     data-release-box
     data-release-url="{{ route('signature-documents.release', [$document, '__SIGNER__']) }}"
     data-cancel-url="{{ route('signature-documents.release.cancel', [$document, '__REQUEST__']) }}"
     data-status-url="{{ route('signature-documents.status', $document) }}"
     data-csrf="{{ csrf_token() }}">

    <h3 class="text-sm font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4">
        Assinatura no tablet
    </h3>

    @if($proximo)
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
            Próximo a assinar: <span class="font-bold text-gray-900 dark:text-white">{{ $proximo->name }}</span>
            <span class="text-gray-400">({{ $proximo->roleLabel() }})</span>
        </p>

        <button type="button" data-release-button data-signer="{{ $proximo->id }}"
                class="w-full px-5 py-3 bg-[#A00001] text-white rounded-xl font-bold text-sm shadow-lg hover:bg-[#800000] transition">
            Liberar para assinatura
        </button>

        <div class="hidden mt-5" data-qr-area>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                    Peça para apontarem a câmera do tablet para este código.
                </p>

                {{-- Fundo branco fixo: um QR sobre fundo escuro não é lido. --}}
                <div class="inline-block bg-white p-3 rounded-lg" data-qr></div>

                <p class="mt-3 text-sm font-bold text-gray-900 dark:text-white" data-qr-countdown></p>
                <p class="text-xs text-gray-500 dark:text-gray-400" data-qr-state>Aguardando leitura…</p>

                <div class="mt-4 flex gap-2 justify-center">
                    <button type="button" data-regenerate
                            class="px-4 py-2 rounded-lg text-xs font-bold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                        Gerar outro código
                    </button>
                    <button type="button" data-cancel-release
                            class="px-4 py-2 rounded-lg text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                        Cancelar liberação
                    </button>
                </div>
            </div>
        </div>

        <p class="hidden mt-3 text-xs text-red-600 dark:text-red-400" data-release-error></p>
    @elseif($document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Todos os signatários já responderam. Aguardando o fechamento do documento.
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $document->isFrozen()
                ? 'Documento em ' . mb_strtolower($document->statusLabel()) . ': não há assinatura a liberar.'
                : 'Congele o documento para liberar a assinatura no tablet.' }}
        </p>
    @endif
</div>

@if($proximo)
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    var box = document.querySelector('[data-release-box]');

    if (!box || typeof QRCode === 'undefined') {
        return;
    }

    var area = box.querySelector('[data-qr-area]');
    var alvo = box.querySelector('[data-qr]');
    var contagem = box.querySelector('[data-qr-countdown]');
    var estado = box.querySelector('[data-qr-state]');
    var erro = box.querySelector('[data-release-error]');
    var csrf = box.dataset.csrf;

    var solicitacaoAtual = null;
    var timer = null;

    function mostraErro(mensagem) {
        erro.textContent = mensagem;
        erro.classList.remove('hidden');
    }

    function limpaErro() {
        erro.classList.add('hidden');
    }

    function desenha(payload) {
        alvo.innerHTML = '';

        // Correção de erro alta: o tablet lê de perto, mas a tela do atendente
        // pega reflexo do balcão.
        new QRCode(alvo, {
            text: payload,
            width: 240,
            height: 240,
            correctLevel: QRCode.CorrectLevel.H,
        });
    }

    function conta(segundos) {
        clearInterval(timer);

        var restante = segundos;

        function passo() {
            if (restante <= 0) {
                clearInterval(timer);
                contagem.textContent = 'Código expirado';
                estado.textContent = 'Gere outro código para continuar.';
                alvo.style.opacity = '0.25';
                return;
            }

            var min = Math.floor(restante / 60);
            var seg = restante % 60;
            contagem.textContent = 'Expira em ' + min + ':' + (seg < 10 ? '0' : '') + seg;
            restante--;
        }

        alvo.style.opacity = '1';
        passo();
        timer = setInterval(passo, 1000);
    }

    function libera(signerId) {
        limpaErro();

        fetch(box.dataset.releaseUrl.replace('__SIGNER__', signerId), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            },
        })
            .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
            .then(function (resultado) {
                if (!resultado.ok) {
                    mostraErro(resultado.body.error || 'Não foi possível liberar a assinatura.');
                    return;
                }

                solicitacaoAtual = resultado.body.request_id;
                area.classList.remove('hidden');
                estado.textContent = 'Aguardando leitura…';
                desenha(resultado.body.qr_payload);
                conta(resultado.body.expires_in);
            })
            .catch(function () {
                mostraErro('Falha de rede ao liberar a assinatura.');
            });
    }

    box.querySelectorAll('[data-release-button]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            libera(botao.dataset.signer);
        });
    });

    var regenerar = box.querySelector('[data-regenerate]');

    if (regenerar) {
        regenerar.addEventListener('click', function () {
            var botao = box.querySelector('[data-release-button]');

            if (botao) {
                libera(botao.dataset.signer);
            }
        });
    }

    var cancelar = box.querySelector('[data-cancel-release]');

    if (cancelar) {
        cancelar.addEventListener('click', function () {
            if (!solicitacaoAtual) {
                return;
            }

            fetch(box.dataset.cancelUrl.replace('__REQUEST__', solicitacaoAtual), {
                method: 'DELETE',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            })
                .then(function () {
                    clearInterval(timer);
                    area.classList.add('hidden');
                    solicitacaoAtual = null;
                })
                .catch(function () {
                    mostraErro('Falha de rede ao cancelar.');
                });
        });
    }
})();
</script>
@endif
