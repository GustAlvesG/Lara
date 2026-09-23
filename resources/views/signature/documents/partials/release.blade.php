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

                {{--
                    Código digitado: só aparece com o modo sem HTTPS ligado
                    (`signature.manual_code.enabled`). É o caminho de quando o
                    tablet não tem câmera — em HTTP comum, getUserMedia não
                    existe. Vence antes do QR, de propósito.
                --}}
                <div class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" data-manual-area>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        Sem câmera no tablet? Dite este código:
                    </p>
                    <p class="text-2xl font-extrabold tracking-[0.25em] text-gray-900 dark:text-white font-mono"
                       data-manual-code></p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" data-manual-countdown></p>
                </div>

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
@endif

@if($proximo || $document->status === \App\Models\SignatureDocument::STATUS_SIGNED)
<script>
(function () {
    var box = document.querySelector('[data-release-box]');

    if (!box) {
        return;
    }

    /*
     * A tela tem duas partes independentes: gerar o QR (só quando há alguém a
     * liberar) e ACOMPANHAR o atendimento (que segue valendo depois da última
     * assinatura, enquanto o PDF final é montado). Por isso os elementos do QR
     * são opcionais daqui para baixo.
     */
    var temQr = typeof QRCode !== 'undefined' && box.querySelector('[data-qr-area]') !== null;

    var area = box.querySelector('[data-qr-area]');
    var alvo = box.querySelector('[data-qr]');
    var contagem = box.querySelector('[data-qr-countdown]');
    var estado = box.querySelector('[data-qr-state]');
    var erro = box.querySelector('[data-release-error]');
    var csrf = box.dataset.csrf;

    var solicitacaoAtual = null;
    var timer = null;

    function mostraErro(mensagem) {
        if (!erro) {
            return;
        }

        erro.textContent = mensagem;
        erro.classList.remove('hidden');
    }

    function limpaErro() {
        if (erro) {
            erro.classList.add('hidden');
        }
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

    var areaCodigo = box.querySelector('[data-manual-area]');
    var textoCodigo = box.querySelector('[data-manual-code]');
    var contagemCodigo = box.querySelector('[data-manual-countdown]');
    var timerCodigo = null;

    /**
     * Exibe o código digitado, em dois blocos de quatro, com contagem própria.
     *
     * O código morre ANTES do QR: ele é ditado em voz alta no balcão, e quem
     * está na fila ouve. Quando vence, some da tela — deixá-lo visível
     * convidaria a ditar um código que não vale mais.
     */
    function mostraCodigo(codigo, segundos) {
        clearInterval(timerCodigo);

        if (!areaCodigo || !codigo) {
            if (areaCodigo) {
                areaCodigo.classList.add('hidden');
            }

            return;
        }

        textoCodigo.textContent = codigo.slice(0, 4) + ' ' + codigo.slice(4);
        areaCodigo.classList.remove('hidden');

        var restante = segundos;

        function passo() {
            if (restante <= 0) {
                clearInterval(timerCodigo);
                textoCodigo.textContent = '— — — —';
                contagemCodigo.textContent = 'Código expirado. Gere outro.';
                return;
            }

            contagemCodigo.textContent = 'Vale por mais ' + restante + 's';
            restante--;
        }

        passo();
        timerCodigo = setInterval(passo, 1000);
    }

    function libera(signerId) {
        if (!temQr) {
            return;
        }

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
                mostraCodigo(resultado.body.manual_code, resultado.body.manual_code_expires_in);
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

    /*
     * Acompanhamento ao vivo.
     *
     * É polling, e não broadcasting: este projeto tem BROADCAST_CONNECTION=log
     * — não há canal em tempo real para usar, e subir um só para esta tela
     * seria mais infraestrutura do que o problema pede. Três segundos é o
     * ritmo do balcão: a pessoa leva minutos lendo o documento.
     *
     * A consulta só roda com a aba visível. Uma tela esquecida aberta a noite
     * inteira consultaria o servidor 28 mil vezes sem ninguém olhando.
     */
    var ultimoEstado = null;

    function acompanha() {
        if (document.hidden) {
            return;
        }

        fetch(box.dataset.statusUrl, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (dados) {
                if (!dados) {
                    return;
                }

                dados.signers.forEach(function (signatario) {
                    var cartao = document.querySelector('[data-signer-card="' + signatario.id + '"]');

                    if (!cartao) {
                        return;
                    }

                    var rotulo = cartao.querySelector('[data-signer-status]');

                    if (rotulo) {
                        rotulo.textContent = signatario.status_label;
                    }

                    var vivo = cartao.querySelector('[data-signer-live]');

                    if (vivo) {
                        var linha = '';

                        if (signatario.request && signatario.request.status === 'consumed') {
                            linha = 'Tablet conectado';

                            if (signatario.last_event) {
                                linha += ' · ' + signatario.last_event.label + ' às ' + signatario.last_event.at;
                            }

                            if (signatario.request.session_remaining > 0) {
                                linha += ' · ' + signatario.request.session_remaining + 's restantes';
                            }
                        } else if (signatario.request && signatario.request.status === 'pending') {
                            linha = 'Aguardando leitura do QR Code';
                        } else if (signatario.last_event) {
                            linha = signatario.last_event.label + ' às ' + signatario.last_event.at;
                        }

                        vivo.textContent = linha;
                        vivo.classList.toggle('hidden', linha === '');
                    }

                    // O tablet leu o QR: o código na tela já não serve para
                    // mais nada, e continuar mostrando convida a fotografá-lo.
                    if (temQr
                        && signatario.request
                        && signatario.request.id === solicitacaoAtual
                        && signatario.request.status !== 'pending') {
                        clearInterval(timer);
                        clearInterval(timerCodigo);
                        contagem.textContent = 'Tablet conectado';
                        estado.textContent = 'O documento está aberto no tablet.';
                        alvo.innerHTML = '';

                        if (areaCodigo) {
                            areaCodigo.classList.add('hidden');
                        }
                    }
                });

                /*
                 | O estado do documento mudou (assinado, recusado, cancelado,
                 | finalizado): recarrega a página em vez de remontar a tela
                 | inteira em JavaScript. São botões, trilha de auditoria e
                 | próximo signatário — e o servidor já sabe montar tudo isso.
                 */
                if (ultimoEstado !== null && dados.status !== ultimoEstado) {
                    window.location.reload();
                    return;
                }

                ultimoEstado = dados.status;
            })
            .catch(function () { /* falha de rede: a próxima volta tenta de novo */ });
    }

    setInterval(acompanha, 3000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            acompanha();
        }
    });
    acompanha();

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
                    clearInterval(timerCodigo);
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
