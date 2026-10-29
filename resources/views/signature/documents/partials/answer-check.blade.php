@php
    /**
     * Conferência das respostas do tablet (SignatureAnswerCheckService), dentro
     * do cartão "Assinatura no tablet".
     *
     * Enquanto a pessoa responde, as respostas aparecem aqui campo a campo
     * (rascunho). Depois de ela tocar em "Continuar", o atendente aprova,
     * devolve com observação ou corrige — e só então o tablet libera o aceite
     * e o traço. O estado chega pelo MESMO polling do acompanhamento
     * (`release.blade.php` dispara `signature:status`); as ações vão por fetch
     * e devolvem o estado novo.
     */
    $haConferencia = $document->status === \App\Models\SignatureDocument::STATUS_AWAITING_SIGNATURE
        && app(\App\Services\Signature\SignatureAnswerCheckService::class)->documentRequires($document);
@endphp

@if($haConferencia)
<div class="hidden mt-5 rounded-xl border border-line p-4 space-y-3" data-answer-check
     data-approve-url="{{ route('signature-documents.answers.approve', $document) }}"
     data-return-url="{{ route('signature-documents.answers.return', $document) }}"
     data-correct-url="{{ route('signature-documents.answers.correct', $document) }}"
     data-csrf="{{ csrf_token() }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-bold text-ink">Conferência das respostas</p>
        <span class="text-xs font-bold" data-ac-status></span>
    </div>
    <p class="text-xs text-ink-2" data-ac-hint></p>

    <dl class="space-y-2 text-sm" data-ac-fields></dl>

    <p class="hidden text-xs rounded-lg bg-subtle px-3 py-2 text-ink-2" data-ac-note></p>

    <div class="hidden flex flex-wrap gap-2" data-ac-actions>
        <button type="button" data-ac-approve
                class="px-4 py-2 rounded-full text-xs font-bold bg-grena text-white hover:bg-grena-hover transition">
            Aprovar respostas
        </button>
        <button type="button" data-ac-open="return"
                class="px-4 py-2 rounded-full text-xs font-bold bg-subtle text-ink hover:bg-line transition">
            Devolver com observação
        </button>
        <button type="button" data-ac-open="correct"
                class="px-4 py-2 rounded-full text-xs font-bold bg-subtle text-ink hover:bg-line transition">
            Corrigir
        </button>
    </div>

    <div class="hidden space-y-2" data-ac-form="return">
        <label class="block text-xs font-bold text-ink-2" for="acNote">O que a pessoa precisa corrigir (aparece no tablet)</label>
        <textarea id="acNote" rows="2" maxlength="500" data-ac-note-input
                  class="w-full rounded-lg border-line-strong text-sm"></textarea>
        <div class="flex gap-2">
            <button type="button" data-ac-return
                    class="px-4 py-2 rounded-full text-xs font-bold bg-grena text-white hover:bg-grena-hover transition">
                Devolver ao tablet
            </button>
            <button type="button" data-ac-close class="px-4 py-2 rounded-full text-xs font-bold text-ink-2 hover:bg-subtle transition">
                Cancelar
            </button>
        </div>
    </div>

    <div class="hidden space-y-3" data-ac-form="correct">
        <p class="text-xs text-ink-2">
            Corrigir já aprova: o documento é refeito com as respostas abaixo, e a pessoa lê de novo antes de assinar.
        </p>
        <div class="space-y-3" data-ac-correct-fields></div>
        <div class="flex gap-2">
            <button type="button" data-ac-correct
                    class="px-4 py-2 rounded-full text-xs font-bold bg-grena text-white hover:bg-grena-hover transition">
                Salvar correção e aprovar
            </button>
            <button type="button" data-ac-close class="px-4 py-2 rounded-full text-xs font-bold text-ink-2 hover:bg-subtle transition">
                Cancelar
            </button>
        </div>
    </div>

    <p class="hidden text-xs text-danger" data-ac-error></p>
</div>

<script>
(function () {
    var box = document.querySelector('[data-answer-check]');

    if (!box) {
        return;
    }

    var csrf = box.dataset.csrf;
    var atual = null;
    // Com um formulário aberto, o polling não refaz a tela embaixo do atendente.
    var editando = false;

    var DICAS = {
        filling: 'A pessoa está respondendo no tablet. As respostas aparecem aqui conforme ela digita.',
        pending: 'Confira as respostas com a pessoa. O tablet só libera a assinatura depois da sua aprovação.',
        returned: 'Devolvido: o tablet voltou às perguntas com o seu recado. Aguarde a pessoa corrigir e enviar de novo.',
        approved: 'Aprovado. A pessoa já pode aceitar e assinar no tablet.',
    };

    function q(sel) { return box.querySelector(sel); }

    function el(tag, classe, texto) {
        var e = document.createElement(tag);

        if (classe) { e.className = classe; }
        if (texto !== undefined) { e.textContent = texto; }

        return e;
    }

    function erro(texto) {
        q('[data-ac-error]').textContent = texto || '';
        q('[data-ac-error]').classList.toggle('hidden', !texto);
    }

    function fechaFormularios() {
        editando = false;
        box.querySelectorAll('[data-ac-form]').forEach(function (f) { f.classList.add('hidden'); });
    }

    function desenha(estado) {
        atual = estado;
        box.classList.toggle('hidden', !estado);

        if (!estado) {
            return;
        }

        q('[data-ac-status]').textContent = estado.status_label
            + (estado.status === 'approved' && estado.checked_by ? ' por ' + estado.checked_by + ' às ' + estado.checked_at : '');
        q('[data-ac-status]').className = 'text-xs font-bold ' + (estado.status === 'approved' ? 'text-ok' : 'text-grena-ink');
        q('[data-ac-hint]').textContent = (estado.status === 'filling' && !estado.in_tablet)
            ? 'Ainda sem respostas. Libere o QR para a pessoa responder no tablet.'
            : (DICAS[estado.status] || '') + (estado.draft_at ? ' Última atualização às ' + estado.draft_at + '.' : '');

        var lista = q('[data-ac-fields]');
        lista.innerHTML = '';

        estado.fields.forEach(function (campo) {
            var linha = el('div', 'rounded-lg bg-subtle px-3 py-2');
            linha.appendChild(el('dt', 'text-xs text-ink-2', campo.question + (campo.required ? ' *' : '')));
            linha.appendChild(el('dd', 'font-semibold text-ink break-words', campo.answer !== '' ? campo.answer : '—'));
            lista.appendChild(linha);
        });

        var nota = q('[data-ac-note]');
        nota.textContent = estado.note ? 'Seu recado: ' + estado.note : '';
        nota.classList.toggle('hidden', !estado.note);

        var podeAgir = estado.status === 'pending' || estado.status === 'returned' || estado.status === 'approved';
        q('[data-ac-actions]').classList.toggle('hidden', !podeAgir);
        q('[data-ac-approve]').classList.toggle('hidden', estado.status === 'approved');
        q('[data-ac-open="return"]').classList.toggle('hidden', estado.status === 'returned');
    }

    // O formulário de correção, montado com os valores gravados (no formato
    // de formulário de cada tipo).
    function montaCorrecao() {
        var alvo = q('[data-ac-correct-fields]');
        alvo.innerHTML = '';

        atual.fields.forEach(function (campo) {
            var bloco = el('div');
            bloco.dataset.key = campo.key;
            bloco.dataset.type = campo.type;
            bloco.appendChild(el('label', 'block text-xs font-bold text-ink-2 mb-1', campo.question));
            var controle;

            if (campo.type === 'checkbox') {
                controle = el('div', 'flex flex-wrap gap-x-4 gap-y-1');
                campo.options.forEach(function (opcao) {
                    var rotulo = el('label', 'flex items-center gap-2 text-sm text-ink');
                    var caixa = el('input', 'rounded border-line-strong');
                    caixa.type = 'checkbox';
                    caixa.value = opcao;
                    caixa.checked = (campo.input || []).indexOf(opcao) !== -1;
                    rotulo.appendChild(caixa);
                    rotulo.appendChild(document.createTextNode(opcao));
                    controle.appendChild(rotulo);
                });
            } else if (campo.type === 'radio' || campo.type === 'yes_no') {
                controle = el('select', 'w-full rounded-lg border-line-strong text-sm');
                var opcoes = campo.type === 'yes_no'
                    ? [['', '—'], ['sim', 'Sim'], ['nao', 'Não']]
                    : [['', '—']].concat(campo.options.map(function (o) { return [o, o]; }));
                opcoes.forEach(function (par) {
                    var o = el('option', '', par[1]);
                    o.value = par[0];
                    controle.appendChild(o);
                });
                controle.value = campo.input || '';
            } else if (campo.type === 'textarea') {
                controle = el('textarea', 'w-full rounded-lg border-line-strong text-sm');
                controle.rows = 3;
                controle.value = campo.input || '';
            } else {
                controle = el('input', 'w-full rounded-lg border-line-strong text-sm');
                controle.type = { date: 'date', date_long: 'date', time: 'time', email: 'email' }[campo.type] || 'text';
                controle.value = campo.input || '';
            }

            controle.dataset.control = '';
            bloco.appendChild(controle);
            bloco.appendChild(el('p', 'hidden mt-1 text-xs text-danger', ''));
            alvo.appendChild(bloco);
        });
    }

    function respostasDaCorrecao() {
        var saida = {};

        box.querySelectorAll('[data-ac-correct-fields] > div').forEach(function (bloco) {
            if (bloco.dataset.type === 'checkbox') {
                saida[bloco.dataset.key] = Array.prototype.map.call(
                    bloco.querySelectorAll('input:checked'), function (c) { return c.value; });
            } else {
                saida[bloco.dataset.key] = bloco.querySelector('[data-control]').value;
            }
        });

        return saida;
    }

    function envia(url, corpo, botao) {
        erro('');
        botao.disabled = true;

        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(corpo || {}),
        })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (b) { return { ok: r.ok, body: b }; }); })
            .then(function (resultado) {
                botao.disabled = false;

                if (!resultado.ok) {
                    erro(resultado.body.error || resultado.body.message || 'Não foi possível registrar a conferência.');

                    return resultado;
                }

                fechaFormularios();
                desenha(resultado.body.answer_check);

                return resultado;
            })
            .catch(function () {
                botao.disabled = false;
                erro('Falha de rede. Tente de novo.');
            });
    }

    q('[data-ac-approve]').addEventListener('click', function () {
        envia(box.dataset.approveUrl, {}, this);
    });

    box.querySelectorAll('[data-ac-open]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            fechaFormularios();
            editando = true;

            if (botao.dataset.acOpen === 'correct') {
                montaCorrecao();
            }

            q('[data-ac-form="' + botao.dataset.acOpen + '"]').classList.remove('hidden');
        });
    });

    box.querySelectorAll('[data-ac-close]').forEach(function (botao) {
        botao.addEventListener('click', fechaFormularios);
    });

    q('[data-ac-return]').addEventListener('click', function () {
        var nota = q('[data-ac-note-input]').value.trim();

        if (nota === '') {
            erro('Escreva para a pessoa o que precisa ser corrigido.');

            return;
        }

        envia(box.dataset.returnUrl, { note: nota }, this).then(function (r) {
            if (r && r.ok) {
                q('[data-ac-note-input]').value = '';
            }
        });
    });

    q('[data-ac-correct]').addEventListener('click', function () {
        envia(box.dataset.correctUrl, { answers: respostasDaCorrecao() }, this).then(function (r) {
            if (!r || r.ok || !r.body.errors) {
                return;
            }

            Object.keys(r.body.errors).forEach(function (chave) {
                var bloco = box.querySelector('[data-ac-correct-fields] > div[data-key="' + chave + '"]');

                if (bloco) {
                    bloco.querySelector('p').textContent = r.body.errors[chave];
                    bloco.querySelector('p').classList.remove('hidden');
                }
            });
        });
    });

    document.addEventListener('signature:status', function (ev) {
        if (!editando) {
            desenha(ev.detail.answer_check || null);
        }
    });
})();
</script>
@endif
