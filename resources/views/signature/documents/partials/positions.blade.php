@php
    /**
     * Onde cada pessoa assina num documento enviado em PDF.
     *
     * No rascunho, o atendente escolhe a pessoa e clica no documento, em cima
     * da linha onde ela assina: o ponto clicado é o meio da BASE da
     * assinatura. Quem fica sem ponto assina na folha de assinaturas que o
     * sistema acrescenta ao fim — ninguém fica sem lugar.
     *
     * A posição é guardada como fração da página (0 a 1), e não em pixels ou
     * milímetros: vale para qualquer tamanho de folha e qualquer zoom da tela.
     *
     * Depois de congelado, o bloco só informa onde cada um assina.
     */
    $editavel = $document->editBlockReason() === null;

    $posicoes = $document->signers->mapWithKeys(fn($s) => [$s->id => $s->signature_position])->all();
@endphp

<div class="bg-surface rounded-card shadow-card p-6" data-positions-box
     data-pdf-url="{{ route('signature-documents.pdf', [$document, 'versao' => 'enviado']) }}"
     data-save-url="{{ route('signature-documents.positions', $document) }}"
     data-csrf="{{ csrf_token() }}"
     data-box-width="{{ \App\Services\Signature\SignaturePdfStamper::SIGNATURE_WIDTH }}"
     data-box-height="{{ \App\Services\Signature\SignaturePdfStamper::SIGNATURE_HEIGHT }}">

    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-2">Lugar das assinaturas</h3>

    @if(!$editavel)
        <ul class="space-y-1 text-sm text-ink">
            @foreach($document->signers as $signer)
                <li>
                    <span class="font-semibold">{{ $signer->name }}</span> —
                    {{ $signer->signature_position ? 'página ' . $signer->signature_position['page'] . ' do documento' : 'folha de assinaturas, ao fim' }}
                </li>
            @endforeach
        </ul>
    @else
        <p class="text-xs text-ink-2 mb-4">
            Escolha a pessoa e clique no documento, em cima da linha onde ela assina. Quem ficar sem lugar marcado
            assina numa <strong>folha de assinaturas</strong> acrescentada ao fim do documento.
        </p>

        <div class="flex flex-wrap gap-2 mb-3" data-position-signers>
            @foreach($document->signers as $signer)
                <button type="button" data-signer="{{ $signer->id }}" data-name="{{ $signer->name }}"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-subtle text-ink hover:bg-line transition">
                    {{ $signer->name }}
                    <span class="font-normal text-ink-2" data-where></span>
                </button>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <button type="button" data-save-positions
                    class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                Salvar lugares
            </button>
            <button type="button" data-clear-position
                    class="px-4 py-2 rounded-lg text-xs font-bold text-danger hover:bg-danger-soft transition">
                Tirar o lugar da pessoa escolhida
            </button>
            <span class="text-xs text-ink-2" data-position-status></span>
        </div>

        {{-- As páginas do PDF, desenhadas pelo PDF.js. Fundo cinza fixo: é papel em cima de mesa, nos dois temas. --}}
        <div data-position-pages style="background:#e9e4e4; padding:12px; border-radius:12px; max-height:80vh; overflow:auto;"></div>
    @endif
</div>

@if($editavel)
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    var box = document.querySelector('[data-positions-box]');

    if (!box || typeof pdfjsLib === 'undefined') {
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    var alvo = box.querySelector('[data-position-pages]');
    var status = box.querySelector('[data-position-status]');
    var botoes = box.querySelectorAll('[data-position-signers] [data-signer]');

    // id do signatário => {page, x, y} (frações da página) ou null
    var posicoes = @json((object) $posicoes);

    var escolhido = botoes.length ? botoes[0].dataset.signer : null;

    // Uma caixa de assinatura tem tamanho fixo no papel (em mm). Na tela, vira
    // fração da página — que cada página informa quando é desenhada.
    var caixaMm = { w: parseFloat(box.dataset.boxWidth), h: parseFloat(box.dataset.boxHeight) };
    var paginas = {};

    function avisa(texto, erro) {
        status.textContent = texto;
        status.style.color = erro ? '#c22b2b' : '';
    }

    function atualizaBotoes() {
        Array.prototype.forEach.call(botoes, function (botao) {
            var p = posicoes[botao.dataset.signer];
            var ativo = botao.dataset.signer === escolhido;

            botao.querySelector('[data-where]').textContent = p ? ' · página ' + p.page : ' · folha de assinaturas';
            botao.style.outline = ativo ? '2px solid #8a1538' : 'none';
        });
    }

    function desenhaMarcas() {
        alvo.querySelectorAll('[data-mark]').forEach(function (m) { m.remove(); });

        Array.prototype.forEach.call(botoes, function (botao) {
            var p = posicoes[botao.dataset.signer];
            var pagina = p ? paginas[p.page] : null;

            if (!pagina) {
                return;
            }

            var w = Math.min(1, caixaMm.w / pagina.mmW);
            var h = Math.min(1, caixaMm.h / pagina.mmH);

            // O ponto guardado é o meio da base da caixa.
            var left = Math.max(0, Math.min(p.x - w / 2, 1 - w));
            var top = Math.max(0, Math.min(p.y - h, 1 - h));

            var marca = document.createElement('div');
            marca.setAttribute('data-mark', '');
            marca.style.cssText = 'position:absolute;border:2px dashed #8a1538;background:rgba(138,21,56,.10);' +
                'color:#8a1538;font:bold 11px sans-serif;padding:2px 4px;pointer-events:none;overflow:hidden;' +
                'left:' + (left * 100) + '%;top:' + (top * 100) + '%;width:' + (w * 100) + '%;height:' + (h * 100) + '%;';
            marca.textContent = botao.dataset.name;

            pagina.el.appendChild(marca);
        });

        atualizaBotoes();
    }

    Array.prototype.forEach.call(botoes, function (botao) {
        botao.addEventListener('click', function () {
            escolhido = botao.dataset.signer;
            atualizaBotoes();
        });
    });

    box.querySelector('[data-clear-position]').addEventListener('click', function () {
        if (escolhido) {
            posicoes[escolhido] = null;
            desenhaMarcas();
            avisa('Não esqueça de salvar.', false);
        }
    });

    box.querySelector('[data-save-positions]').addEventListener('click', function () {
        avisa('Salvando…', false);

        fetch(box.dataset.saveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': box.dataset.csrf
            },
            body: JSON.stringify({ positions: posicoes })
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
            .then(function (r) {
                avisa(r.ok ? 'Lugares salvos.' : (r.json.message || r.json.error || 'Não foi possível salvar.'), !r.ok);
            })
            .catch(function () { avisa('Falha de rede ao salvar.', true); });
    });

    function desenhaPagina(pdf, numero, largura) {
        return pdf.getPage(numero).then(function (pagina) {
            var base = pagina.getViewport({ scale: 1 });
            var viewport = pagina.getViewport({ scale: largura / base.width });

            var moldura = document.createElement('div');
            moldura.style.cssText = 'position:relative;margin:0 auto 12px;background:#fff;cursor:crosshair;' +
                'box-shadow:0 1px 6px rgba(0,0,0,.25);width:' + viewport.width + 'px;height:' + viewport.height + 'px;';

            var canvas = document.createElement('canvas');
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.cssText = 'display:block;width:100%;height:100%;';
            moldura.appendChild(canvas);

            // Pontos do PDF (72 por polegada) em milímetros.
            paginas[numero] = { el: moldura, mmW: base.width * 25.4 / 72, mmH: base.height * 25.4 / 72 };

            moldura.addEventListener('click', function (ev) {
                if (!escolhido) {
                    return;
                }

                var r = moldura.getBoundingClientRect();

                posicoes[escolhido] = {
                    page: numero,
                    x: Math.round(((ev.clientX - r.left) / r.width) * 100000) / 100000,
                    y: Math.round(((ev.clientY - r.top) / r.height) * 100000) / 100000
                };

                desenhaMarcas();
                avisa('Não esqueça de salvar.', false);
            });

            alvo.appendChild(moldura);

            return pagina.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
        });
    }

    atualizaBotoes();
    avisa('Carregando o documento…', false);

    pdfjsLib.getDocument({ url: box.dataset.pdfUrl, withCredentials: true }).promise
        .then(function (pdf) {
            var largura = Math.max(320, Math.min(alvo.clientWidth - 24, 760));
            var cadeia = Promise.resolve();

            // Em sequência, para as páginas entrarem na ordem.
            for (var n = 1; n <= pdf.numPages; n++) {
                (function (numero) {
                    cadeia = cadeia.then(function () { return desenhaPagina(pdf, numero, largura); });
                })(n);
            }

            return cadeia.then(function () {
                desenhaMarcas();
                avisa('', false);
            });
        })
        .catch(function () {
            avisa('Não foi possível abrir o PDF para marcar os lugares.', true);
        });
})();
</script>
@endif
