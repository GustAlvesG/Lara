{{--
    Comportamento do editor do contrato. Sem lógica de contrato aqui: a
    numeração, as variáveis e os problemas vêm da prévia do servidor
    (ContractController::preview). O script só monta linhas, move, sincroniza
    o texto rico com o textarea e pede a prévia.

    Desde 05/11/2026 também: abas ao lado (Prévia | Conferência | Claude),
    rolar a prévia até a cláusula em que se digita, salvar automático com
    aviso de conflito (`contracts.autosave`) e as ajudas das variáveis
    (criar do [[marcador]], tipo pelo nome, variável comum, onde é usada,
    "Falta preencher").

    Desde 06/11/2026 serve também à edição do modelo (model-edit): sem
    `urls.autosave`, não salva sozinho; sem `[data-var-missing]`, não há
    "Falta preencher".
--}}
<script>
(function () {
    var CT = @json($ctConfig);
    var form = document.getElementById('ctForm');
    if (!form) return;

    var paper = document.querySelector('[data-preview-paper]');
    var problemsBox = document.querySelector('[data-problems]');
    var estado = document.querySelector('[data-save-state]');
    var alterado = false;
    // Cada alteração sobe este número: o salvar automático sabe se algo mudou enquanto gravava.
    var edicao = 0;

    function chave() { return 'n' + Math.random().toString(36).slice(2, 11); }

    // Serve para texto e para valor de atributo: o innerHTML não escapa aspas.
    function escape(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML.replace(/"/g, '&quot;');
    }

    // O que a biblioteca e o cadastro de contratadas devolveram: o botão leva só o índice.
    var listaBiblioteca = [];
    var listaPartes = [];
    // Depois de qual cláusula entra a da biblioteca (null = no fim).
    var depoisDe = null;

    function insereClausula(card, depois) {
        if (depois) {
            depois.after(card);
        } else {
            document.querySelector('[data-list="clause"]').appendChild(card);
        }
    }

    function molde(id, trocas) {
        var html = document.getElementById(id).innerHTML;
        Object.keys(trocas).forEach(function (k) { html = html.split(k).join(trocas[k]); });
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    function chaveDaClausula(card) {
        var campo = card.querySelector('input[name$="[uid]"]');
        var m = campo && campo.name.match(/^clauses\[([^\]]+)\]/);
        return m ? m[1] : chave();
    }

    function novoItem(card, html, nivel) {
        var k = chave();
        var item = molde('tpl-item', { '__C__': chaveDaClausula(card), '__K__': k });
        card.querySelector('[data-items]').appendChild(item);
        if (html) {
            item.querySelector('.ct-rich').innerHTML = html;
        }
        if (nivel === 2) aplicaNivel(item, 2);
        preencheSelects(item);
        return item;
    }

    function aplicaNivel(item, nivel) {
        item.querySelector('[data-level]').value = nivel;
        item.classList.toggle('ml-8', nivel === 2);
        item.querySelector('[data-indent]').textContent = nivel === 2 ? '← Item' : 'Subitem →';
    }

    function renumera() {
        document.querySelectorAll('[data-list="clause"] > [data-row="clause"]').forEach(function (card, i) {
            var rotulo = card.querySelector('[data-clause-number]');
            if (rotulo) rotulo.textContent = 'Cláusula ' + (i + 1);
        });
    }

    function sincroniza() {
        form.querySelectorAll('.ct-rich').forEach(function (ed) {
            var fonte = ed.parentNode.querySelector('[data-rich-source]');
            if (fonte) fonte.value = ed.innerHTML;
        });
    }

    // ---- Selects "Inserir variável" e "Inserir remissão" ----
    function preencheSelects(raiz) {
        (raiz || document).querySelectorAll('select[data-insert="token"]').forEach(function (s) {
            s.innerHTML = '<option value="">Inserir variável…</option>' + CT.tokens.map(function (t) {
                return '<option value="' + escape(t.token) + '">' + escape(t.label) + ' — ' + escape(t.token) + '</option>';
            }).join('');
        });
        (raiz || document).querySelectorAll('select[data-insert="ref"]').forEach(function (s) {
            s.innerHTML = '<option value="">Inserir remissão…</option>' + CT.outline.map(function (o) {
                return '<option value="[[ref:' + escape(o.uid) + ']]">' + escape(o.label) + '</option>';
            }).join('');
        });
    }

    // A seleção dentro do texto rico é guardada: escolher no select tira o foco dele.
    var ultima = null;
    document.addEventListener('selectionchange', function () {
        var sel = window.getSelection();
        if (!sel.rangeCount) return;
        var no = sel.anchorNode;
        var el = no && (no.nodeType === 1 ? no : no.parentElement);
        var ed = el && el.closest('.ct-rich');
        if (ed) ultima = { ed: ed, range: sel.getRangeAt(0).cloneRange() };
    });

    function focaNoFim(ed) {
        ed.focus();
        var r = document.createRange();
        r.selectNodeContents(ed);
        r.collapse(false);
        var sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
    }

    document.addEventListener('mousedown', function (e) {
        // Botão da barra não pode roubar a seleção do texto.
        if (e.target.closest('[data-cmd]')) e.preventDefault();
    });

    document.addEventListener('click', function (e) {
        var cmd = e.target.closest('[data-cmd]');
        if (cmd) {
            var ed = cmd.closest('.ct-editor').querySelector('.ct-rich');
            if (document.activeElement !== ed) focaNoFim(ed);
            document.execCommand(cmd.dataset.cmd, false, null);
            marca();
            return;
        }

        var aba = e.target.closest('[data-side-tab]');
        if (aba) {
            abreAba(aba.dataset.sideTab);
            return;
        }

        var falta = e.target.closest('[data-var-focus]');
        if (falta) {
            var linhaVar = document.querySelectorAll('[data-list="variable"] > [data-row="variable"]')[parseInt(falta.dataset.varFocus, 10)];
            if (linhaVar) {
                linhaVar.scrollIntoView({ behavior: 'smooth', block: 'center' });
                linhaVar.querySelector('[data-var-value]').focus({ preventScroll: true });
            }
            return;
        }

        if (e.target.closest('[data-conflict-reload]')) {
            alterado = false;
            location.reload();
            return;
        }

        if (e.target.closest('[data-conflict-force]')) {
            if (!confirm('Gravar o seu texto por cima do que a outra pessoa salvou?')) return;
            conflito = false;
            document.querySelector('[data-conflict]').classList.add('hidden');
            salvaAuto(true);
            return;
        }

        var add = e.target.closest('[data-add]');
        if (add) {
            var tipo = add.dataset.add;
            var k = chave();
            var linha = molde('tpl-' + tipo, { '__K__': k });
            if (tipo === 'clause') {
                // O botão de dentro de uma cláusula põe a nova logo abaixo dela.
                insereClausula(linha, add.closest('[data-row="clause"]'));
                novoItem(linha);
            } else {
                document.querySelector('[data-list="' + tipo + '"]').appendChild(linha);
            }
            preencheSelects(linha);
            linha.querySelectorAll('textarea[data-autogrow]').forEach(cresce);
            renumera();
            var foco = linha.querySelector('input[type="text"], .ct-rich');
            if (foco) foco.focus();
            marca(true);
            return;
        }

        var addItem = e.target.closest('[data-add-item]');
        if (addItem) {
            novoItem(addItem.closest('[data-row="clause"]')).querySelector('.ct-rich').focus();
            marca(true);
            return;
        }

        var mover = e.target.closest('[data-move]');
        if (mover) {
            var row = mover.closest('.ct-row');
            if (mover.dataset.move === 'up' && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            } else if (mover.dataset.move === 'down' && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }
            renumera();
            marca(true);
            return;
        }

        var remover = e.target.closest('[data-remove]');
        if (remover) {
            if (remover.dataset.confirm && !confirm(remover.dataset.confirm)) return;
            var removida = remover.closest('.ct-row');
            // O painel da biblioteca pode estar dentro dela: volta para o topo.
            var painelDentro = removida.querySelector('[data-library-panel]');
            if (painelDentro) {
                painelDentro.classList.add('hidden');
                document.querySelector('[data-library-home]').appendChild(painelDentro);
            }
            removida.remove();
            renumera();
            marca(true);
            return;
        }

        var recuo = e.target.closest('[data-indent]');
        if (recuo) {
            var item = recuo.closest('[data-row="item"]');
            aplicaNivel(item, item.querySelector('[data-level]').value === '2' ? 1 : 2);
            marca(true);
            return;
        }

        var resolver = e.target.closest('[data-resolve]');
        if (resolver) {
            fetch(resolver.dataset.resolve, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CT.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ resolved: true })
            }).then(function (r) {
                if (r.ok) resolver.closest('[data-comment]').remove();
            });
            return;
        }

        var salvar = e.target.closest('[data-save-library]');
        if (salvar) {
            salvaNaBiblioteca(salvar.closest('[data-row="clause"]'), salvar);
            return;
        }

        var biblioteca = e.target.closest('[data-library-open]');
        if (biblioteca) {
            abreBiblioteca(biblioteca);
            return;
        }

        var daBiblioteca = e.target.closest('[data-library-pick]');
        if (daBiblioteca) {
            var escolhida = listaBiblioteca[parseInt(daBiblioteca.dataset.libraryPick, 10)];
            document.querySelector('[data-library-panel]').classList.add('hidden');
            if (escolhida) insereDaBiblioteca(escolhida);
            return;
        }

        var cadastro = e.target.closest('[data-party-pick]');
        if (cadastro) {
            var parte = listaPartes[parseInt(cadastro.dataset.partyPick, 10)];
            if (!parte) return;
            preencheParte(cadastro.closest('[data-row="party"]'), parte);
            cadastro.closest('[data-party-results]').innerHTML = '';
            marca(true);
            return;
        }

        if (e.target.closest('[data-preview]')) {
            atualizaPrevia();
            return;
        }

        if (e.target.closest('[data-submit-review]')) {
            form.querySelector('[data-next]').value = 'submit';
        }

        // Clique fora fecha o painel da biblioteca.
        var painel = document.querySelector('[data-library-panel]');
        if (painel && !e.target.closest('[data-library-panel]')) painel.classList.add('hidden');
    });

    document.addEventListener('change', function (e) {
        // Variável comum: entra pronta, com nome e tipo (ou vai para a que já existe).
        var comum = e.target.closest('[data-common-variable]');
        if (comum) {
            var escolhida = CT.common[parseInt(comum.value, 10)];
            comum.value = '';
            if (escolhida) adicionaVariavel(escolhida.label, escolhida.type, { foco: true });
            return;
        }

        // Tipo escolhido à mão: o palpite pelo nome não mexe mais.
        var tipoVar = e.target.closest('[data-var-type]');
        if (tipoVar) {
            tipoVar.dataset.touched = '1';
            delete tipoVar.closest('[data-row="variable"]').dataset.auto;
        }

        var ins = e.target.closest('select[data-insert]');
        if (ins && ins.value) {
            var ed = ins.closest('.ct-editor').querySelector('.ct-rich');
            if (ultima && ultima.ed === ed) {
                ed.focus();
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(ultima.range);
            } else {
                focaNoFim(ed);
            }
            document.execCommand('insertText', false, ins.value);
            ins.value = '';
            marca();
            return;
        }

        var tipoParte = e.target.closest('[data-party-kind]');
        if (tipoParte) {
            var linha = tipoParte.closest('[data-row="party"]');
            linha.querySelectorAll('[data-only="pf"]').forEach(function (el) { el.classList.toggle('hidden', tipoParte.value !== 'pf'); });
            linha.querySelectorAll('[data-only="pj"]').forEach(function (el) { el.classList.toggle('hidden', tipoParte.value !== 'pj'); });
        }

        var fonte = e.target.closest('[data-signer-source]');
        if (fonte) {
            var s = fonte.closest('[data-row="signer"]');
            s.querySelectorAll('[data-only-source]').forEach(function (el) {
                el.classList.toggle('hidden', el.dataset.onlySource !== fonte.value);
            });
            s.querySelector('[data-person-name]').disabled = fonte.value !== 'person';
            s.querySelector('[data-director-name]').disabled = fonte.value !== 'director';
        }

        var diretor = e.target.closest('select[name$="[director]"]');
        if (diretor) {
            var opcao = diretor.options[diretor.selectedIndex];
            diretor.closest('[data-row="signer"]').querySelector('[data-director-name]').value =
                // trim ANTES: a quebra de linha do <option> impedia o regex de tirar o "(Titular)" (05/11/2026).
                diretor.value ? opcao.textContent.trim().replace(/\s*\([^)]*\)[\s\S]*$/, '').trim() : '';
        }

        marca(true);
    });

    // ---- Colar (do Word, do PDF, de outro contrato) ----
    // Fica só o que o contrato usa: negrito, itálico, sublinhado, parágrafo e lista.
    // O servidor saneia de novo ao salvar (ContractContent::html).
    var PERMITIDAS = { B: 1, STRONG: 1, I: 1, EM: 1, U: 1, P: 1, BR: 1, UL: 1, OL: 1, LI: 1 };

    function limpaColado(html) {
        var t = document.createElement('template');
        t.innerHTML = html;
        (function limpa(no) {
            Array.prototype.slice.call(no.childNodes).forEach(function (filho) {
                if (filho.nodeType === 8 || (filho.nodeType === 1 && /^(STYLE|SCRIPT|META|LINK|TITLE|XML)$/.test(filho.tagName))) {
                    filho.remove();
                    return;
                }
                if (filho.nodeType !== 1) return;
                limpa(filho);
                var tag = filho.tagName === 'DIV' || /^H\d$/.test(filho.tagName) ? 'P' : filho.tagName;
                if (!PERMITIDAS[tag]) {
                    filho.replaceWith.apply(filho, Array.prototype.slice.call(filho.childNodes));
                    return;
                }
                var limpo = document.createElement(tag);
                while (filho.firstChild) limpo.appendChild(filho.firstChild);
                filho.replaceWith(limpo);
            });
        })(t.content);
        var d = document.createElement('div');
        d.appendChild(t.content);
        return d.innerHTML.replace(/<p>(\s|&nbsp;)*<\/p>/g, '');
    }

    form.addEventListener('paste', function (e) {
        var ed = e.target.closest && e.target.closest('.ct-rich');
        if (!ed || !e.clipboardData) return;
        var html = e.clipboardData.getData('text/html');
        var texto = e.clipboardData.getData('text/plain');
        e.preventDefault();
        if (html) {
            document.execCommand('insertHTML', false, limpaColado(html));
        } else {
            document.execCommand('insertText', false, texto);
        }
        marca(true);
    });

    // ---- Caixas que crescem com o texto (título, endereço, qualificação) ----
    // São campos de uma linha no contrato: a quebra de linha vira espaço.
    function cresce(campo) {
        campo.style.height = 'auto';
        campo.style.height = (campo.scrollHeight + 2) + 'px';
    }

    form.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.matches('textarea[data-autogrow]')) e.preventDefault();
    });

    form.addEventListener('input', function (e) {
        if (e.target.closest('[data-party-search]')) {
            buscaParte(e.target);
            return;
        }
        if (e.target.matches('textarea[data-autogrow]')) {
            if (/[\r\n]/.test(e.target.value)) e.target.value = e.target.value.replace(/\s*[\r\n]+\s*/g, ' ');
            cresce(e.target);
        }

        var linhaVar = e.target.closest('[data-row="variable"]');
        if (linhaVar) {
            // Mexeu: deixa de ser a variável criada sozinha do [[marcador]] (não some mais).
            delete linhaVar.dataset.auto;
            if (e.target.matches('[data-var-label]')) palpiteDoTipo(linhaVar);
            if (e.target.matches('[data-var-value]')) desenhaFaltaPreencher();
        }

        var clausula = e.target.closest('[data-row="clause"]');
        if (clausula) ativaClausula(clausula.dataset.uid, false);

        marca(true);
    });

    // Entrar numa cláusula leva a prévia até ela (05/11/2026).
    form.addEventListener('focusin', function (e) {
        var clausula = e.target.closest('[data-row="clause"]');
        if (clausula) ativaClausula(clausula.dataset.uid, true);
    });

    form.querySelectorAll('textarea[data-autogrow]').forEach(cresce);
    // Editor em passos: escondida, a caixa mede zero; ao abrir o passo, mede de novo.
    form.addEventListener('step:shown', function (e) {
        e.target.querySelectorAll('textarea[data-autogrow]').forEach(cresce);
    });
    window.addEventListener('resize', function () { form.querySelectorAll('textarea[data-autogrow]').forEach(cresce); });

    // ---- Ocultar / mostrar a prévia (tela larga), lembrado neste navegador ----
    var layout = document.querySelector('[data-layout]');
    var alterna = document.querySelector('[data-preview-toggle]');
    function mostraPrevia(oculta) {
        layout.classList.toggle('ct-solo', oculta);
        if (alterna) alterna.textContent = oculta ? 'Mostrar prévia' : 'Ocultar prévia';
        try { localStorage.setItem('ct-preview-hidden', oculta ? '1' : '0'); } catch (err) {}
    }
    if (layout && alterna) {
        var guardado = null;
        try { guardado = localStorage.getItem('ct-preview-hidden'); } catch (err) {}
        if (guardado === '1') mostraPrevia(true);
        alterna.addEventListener('click', function () { mostraPrevia(!layout.classList.contains('ct-solo')); });
    }

    var enviando = false;
    var aposSalvar = null;
    form.addEventListener('submit', function (e) {
        // Um salvar automático em andamento muda a revisão: espera ele, senão o envio
        // daria "alguém salvou antes de você" por causa da própria pessoa.
        if (salvando) {
            e.preventDefault();
            var botao = e.submitter || null;
            aposSalvar = function () {
                if (botao) botao.disabled = false;
                form.requestSubmit(botao);
            };
            return;
        }
        sincroniza();
        alterado = false;
        enviando = true;
        clearTimeout(esperaSalvar);
    });

    window.addEventListener('beforeunload', function (e) {
        if (alterado && !enviando) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---- Abas ao lado: Prévia | Conferência | Claude (05/11/2026) ----
    var aside = document.querySelector('[data-side]');
    function abreAba(nome) {
        document.querySelectorAll('[data-side-tab]').forEach(function (b) {
            b.setAttribute('aria-selected', b.dataset.sideTab === nome ? 'true' : 'false');
        });
        document.querySelectorAll('[data-side-panel]').forEach(function (p) {
            p.classList.toggle('hidden', p.dataset.sidePanel !== nome);
        });
        if (aside && colunaPresa()) aside.scrollTop = 0;
        if (nome === 'previa' && clausulaAtiva) rolaPrevia(clausulaAtiva, false);
    }
    // Voltando da revisão do Claude ("Salvar e analisar"), abre a aba dela.
    if (location.hash === '#revisao-ia' && document.querySelector('[data-side-tab="claude"]')) abreAba('claude');

    // ---- A prévia acompanha a cláusula em que se digita (05/11/2026) ----
    var clausulaAtiva = null;

    // Tela larga: a coluna da prévia rola sozinha (split-pane). Na estreita, ela fica embaixo do texto.
    function colunaPresa() {
        return aside && getComputedStyle(aside).position === 'sticky';
    }

    function ativaClausula(uid, mudouDeLugar) {
        if (!uid) return;
        var nova = uid !== clausulaAtiva;
        clausulaAtiva = uid;
        if (nova || mudouDeLugar) rolaPrevia(uid, nova);
    }

    function rolaPrevia(uid, destaca) {
        if (!paper || !colunaPresa()) return;
        var painel = paper.closest('[data-side-panel]');
        if (!painel || painel.classList.contains('hidden')) return;
        if (document.querySelector('[data-layout]').classList.contains('ct-solo')) return;

        paper.querySelectorAll('.ct-clause.ct-active').forEach(function (c) { c.classList.remove('ct-active'); });
        var alvo = paper.querySelector('[data-clause="' + uid + '"]');
        if (!alvo) return;
        alvo.classList.add('ct-active');

        var topo = aside.scrollTop + alvo.getBoundingClientRect().top - aside.getBoundingClientRect().top - 56;
        aside.scrollTo({ top: Math.max(0, topo), behavior: 'smooth' });

        if (destaca) {
            alvo.classList.add('ct-flash');
            setTimeout(function () { alvo.classList.remove('ct-flash'); }, 900);
        }
    }

    // ---- Prévia (servidor) e salvar automático ----
    var espera = null;
    var esperaSalvar = null;
    function marca(previa) {
        alterado = true;
        edicao++;
        if (estado && !conflito) estado.textContent = 'Alterações ainda não salvas…';
        if (previa !== false) {
            clearTimeout(espera);
            espera = setTimeout(atualizaPrevia, 1500);
        }
        // Sem `autosave` (edição do modelo, 06/11/2026): grava só no botão Salvar.
        if (!conflito && CT.urls.autosave) {
            clearTimeout(esperaSalvar);
            esperaSalvar = setTimeout(function () { salvaAuto(false); }, 3000);
        }
    }

    function atualizaPrevia() {
        sincroniza();
        var dados = new FormData(form);
        // O formulário é PUT (update); a prévia é POST.
        dados.delete('_method');
        fetch(CT.urls.preview, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CT.csrf, 'Accept': 'application/json' },
            body: dados
        }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
            if (!j) return;
            paper.innerHTML = j.html;
            CT.tokens = j.tokens;
            CT.outline = j.outline;
            CT.usage = j.usage || {};
            preencheSelects();
            desenhaProblemas(j.problems);
            guardaChaves(j.variables || []);
            // Antes de criar: a criada agora ainda não aparece no uso desta prévia.
            limpaAutomaticas();
            var criadas = criaDasMarcas(j.unknown || []);
            desenhaUso();
            desenhaFaltaPreencher();
            if (clausulaAtiva) rolaPrevia(clausulaAtiva, false);
            // Variável nova: a prévia de novo, já com ela (e com o tipo do palpite).
            if (criadas) marca(true);
        });
    }

    // Grava sem sair da tela. `forca`: depois do aviso de conflito, a pessoa escolheu gravar por cima.
    var conflito = false;
    var salvando = false;
    var revisao = document.querySelector('[data-revision]');
    function salvaAuto(forca) {
        if (enviando || (conflito && !forca)) return;
        if (salvando) {
            clearTimeout(esperaSalvar);
            esperaSalvar = setTimeout(function () { salvaAuto(forca); }, 1000);
            return;
        }
        salvando = true;
        var versao = edicao;
        sincroniza();
        var dados = new FormData(form);
        dados.set('_method', 'PUT');
        dados.set('revision', String(CT.revision));
        if (forca) dados.set('force', '1');
        if (estado) estado.textContent = 'Salvando…';

        fetch(CT.urls.autosave, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CT.csrf, 'Accept': 'application/json' },
            body: dados
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (j) { return { status: r.status, j: j }; });
        }).then(function (res) {
            salvando = false;
            if (aposSalvar && !(res.status === 409)) {
                var segue = aposSalvar;
                aposSalvar = null;
                if (res.status === 200 && res.j.ok) {
                    CT.revision = res.j.revision;
                    if (revisao) revisao.value = res.j.revision;
                }
                segue();
                return;
            }
            aposSalvar = null;
            if (res.status === 200 && res.j.ok) {
                CT.revision = res.j.revision;
                if (revisao) revisao.value = res.j.revision;
                guardaChaves(res.j.variables || []);
                if (edicao === versao) alterado = false;
                if (estado) estado.textContent = edicao === versao
                    ? 'Salvo automaticamente às ' + res.j.saved_at + '.'
                    : 'Alterações ainda não salvas…';
                return;
            }
            if (res.status === 409) {
                mostraConflito(res.j.error || 'Não foi possível salvar.', !!res.j.conflict);
                return;
            }
            if (res.status === 422) {
                if (estado) estado.textContent = 'Não salvou: ' + ((res.j && res.j.message) || 'confira os campos.');
                return;
            }
            if (res.status === 419 || res.status === 401) {
                mostraConflito('A sua sessão expirou. Copie o que digitou por último e recarregue a página para entrar de novo.', false);
                return;
            }
            tentaDeNovo();
        }).catch(function () {
            salvando = false;
            if (aposSalvar) {
                var segue = aposSalvar;
                aposSalvar = null;
                segue();
                return;
            }
            tentaDeNovo();
        });
    }

    function tentaDeNovo() {
        if (estado) estado.textContent = 'Sem conexão para salvar agora — tentando de novo…';
        clearTimeout(esperaSalvar);
        esperaSalvar = setTimeout(function () { salvaAuto(false); }, 10000);
    }

    function mostraConflito(mensagem, podeForcar) {
        conflito = true;
        clearTimeout(esperaSalvar);
        var caixa = document.querySelector('[data-conflict]');
        caixa.querySelector('[data-conflict-message]').textContent = mensagem;
        caixa.querySelector('[data-conflict-force]').classList.toggle('hidden', !podeForcar);
        caixa.querySelector('[data-conflict-hint]').classList.toggle('hidden', !podeForcar);
        caixa.classList.remove('hidden');
        caixa.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (estado) estado.textContent = 'Não salvo: veja o aviso no alto da tela.';
    }

    // ---- Variáveis (05/11/2026) ----
    function linhasVariaveis() {
        return Array.prototype.slice.call(document.querySelectorAll('[data-list="variable"] > [data-row="variable"]'));
    }

    // A ordem das chaves que o servidor devolve é a das linhas que ele aceitou (pula as em branco).
    function linhasAceitas() {
        return linhasVariaveis().filter(function (l) {
            return l.querySelector('[data-var-label]').value.trim() !== '' || l.querySelector('[data-var-key]').value !== '';
        });
    }

    // Linha nova ganha a chave depois que o nome para de mudar: trocar o nome depois não quebra o [[marcador]].
    function guardaChaves(chaves) {
        linhasAceitas().forEach(function (l, i) {
            var campo = l.querySelector('[data-var-key]');
            if (campo.value || !chaves[i] || l.contains(document.activeElement)) return;
            campo.value = chaves[i];
            var token = l.querySelector('[data-var-token]');
            token.textContent = '[[' + chaves[i] + ']]';
            token.classList.remove('hidden');
        });
    }

    function semAcento(texto) {
        return String(texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function adivinhaTipo(nome) {
        var limpo = semAcento(nome);
        var tipos = Object.keys(CT.guess || {});
        for (var i = 0; i < tipos.length; i++) {
            if (new RegExp(CT.guess[tipos[i]]).test(limpo)) return tipos[i];
        }
        return 'text';
    }

    function palpiteDoTipo(linha) {
        var tipo = linha.querySelector('[data-var-type]');
        if (tipo.dataset.touched) return;
        tipo.value = adivinhaTipo(linha.querySelector('[data-var-label]').value);
    }

    // Variável nova (comum, ou do [[marcador]]). Se o nome já existe, vai para ela.
    function adicionaVariavel(nome, tipo, opcoes) {
        opcoes = opcoes || {};
        var existente = linhasVariaveis().filter(function (l) {
            var chave = l.querySelector('[data-var-key]').value;
            return semAcento(l.querySelector('[data-var-label]').value) === semAcento(nome) || (opcoes.chave && chave === opcoes.chave);
        })[0];
        if (existente) {
            if (opcoes.foco) existente.querySelector('[data-var-value]').focus();
            return null;
        }
        var linha = molde('tpl-variable', { '__K__': chave() });
        document.querySelector('[data-list="variable"]').appendChild(linha);
        linha.querySelector('[data-var-label]').value = nome;
        var campoTipo = linha.querySelector('[data-var-type]');
        campoTipo.value = tipo || adivinhaTipo(nome);
        if (opcoes.chave) {
            linha.querySelector('[data-var-key]').value = opcoes.chave;
            var token = linha.querySelector('[data-var-token]');
            token.textContent = '[[' + opcoes.chave + ']]';
            token.classList.remove('hidden');
        }
        if (opcoes.auto) {
            linha.dataset.auto = '1';
        } else {
            campoTipo.dataset.touched = '1';
        }
        if (opcoes.foco) {
            linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
            linha.querySelector('[data-var-value]').focus({ preventScroll: true });
        }
        marca(true);
        return linha;
    }

    // [[Prazo de vigência]] escrito na cláusula e sem variável: ela aparece sozinha.
    function criaDasMarcas(desconhecidas) {
        var criadas = 0;
        desconhecidas.forEach(function (v) {
            if (adicionaVariavel(v.label, v.type, { chave: v.key, auto: true })) criadas++;
        });
        desenhaFaltaPreencher();
        return criadas;
    }

    // A criada sozinha que ninguém preencheu e o texto deixou de usar (o [[marcador]] foi
    // apagado ou ainda estava sendo digitado) some de novo.
    function limpaAutomaticas() {
        linhasVariaveis().forEach(function (l) {
            if (!l.dataset.auto || l.contains(document.activeElement)) return;
            var chaveVar = l.querySelector('[data-var-key]').value;
            if (l.querySelector('[data-var-value]').value.trim() === '' && chaveVar && !(CT.usage || {})[chaveVar]) {
                l.remove();
            }
        });
    }

    function desenhaUso() {
        linhasVariaveis().forEach(function (l) {
            var chaveVar = l.querySelector('[data-var-key]').value;
            var campo = l.querySelector('[data-var-usage]');
            if (!chaveVar) {
                campo.textContent = '';
                return;
            }
            var usos = (CT.usage || {})[chaveVar] || [];
            campo.textContent = usos.length ? 'Usada em: ' + usos.join(', ') + '.' : 'Ainda não usada no texto.';
            campo.classList.toggle('text-warn', !usos.length);
            campo.classList.toggle('text-ink-3', !!usos.length);
        });
    }

    function desenhaFaltaPreencher() {
        var caixa = document.querySelector('[data-var-missing]');
        if (!caixa) return;
        var faltam = [];
        linhasVariaveis().forEach(function (l, i) {
            var nome = l.querySelector('[data-var-label]').value.trim();
            if (nome && l.querySelector('[data-var-value]').value.trim() === '') faltam.push({ i: i, nome: nome });
        });
        caixa.classList.toggle('hidden', !faltam.length);
        caixa.innerHTML = faltam.length
            ? '<p class="mb-1 text-xs font-bold text-warn">Falta preencher (' + faltam.length + ')</p><div class="flex flex-wrap gap-1.5">'
                + faltam.map(function (f) {
                    return '<button type="button" class="rounded-full bg-surface px-3 py-1 text-xs font-bold text-ink hover:bg-subtle" data-var-focus="' + f.i + '">' + escape(f.nome) + '</button>';
                }).join('') + '</div>'
            : '';
    }

    function desenhaProblemas(lista) {
        var erros = lista.filter(function (p) { return p.level === 'error'; });
        var avisos = lista.filter(function (p) { return p.level === 'warning'; });
        var contador = document.querySelector('[data-problem-count]');
        if (contador) {
            contador.textContent = erros.length ? erros.length : (avisos.length ? avisos.length : '✓');
            contador.className = 'rounded-full px-2 text-[11px] ' + (erros.length ? 'bg-danger text-white' : (avisos.length ? 'bg-warn-soft text-warn' : 'bg-ok-soft text-ok'));
        }
        if (!problemsBox) return;
        var html = '';
        if (!erros.length && !avisos.length) {
            html = '<p class="rounded-xl bg-ok-soft px-3 py-2 text-sm font-bold text-ok">Nenhum problema encontrado.</p>';
        }
        if (erros.length) {
            html += '<p class="mb-1 text-xs font-bold uppercase tracking-wider text-danger">Falta corrigir (' + erros.length + ')</p><ul class="mb-3 space-y-1 text-sm text-ink">'
                + erros.map(function (p) { return '<li class="rounded-lg bg-danger-soft px-3 py-1.5">' + escape(p.message) + '</li>'; }).join('') + '</ul>';
        }
        if (avisos.length) {
            html += '<p class="mb-1 text-xs font-bold uppercase tracking-wider text-warn">Vale conferir (' + avisos.length + ')</p><ul class="space-y-1 text-sm text-ink">'
                + avisos.map(function (p) { return '<li class="rounded-lg bg-warn-soft px-3 py-1.5">' + escape(p.message) + '</li>'; }).join('') + '</ul>';
        }
        problemsBox.innerHTML = html;
    }

    // ---- Biblioteca ----
    // Um painel só: vai para junto do botão clicado (topo, entre cláusulas ou fim).
    function abreBiblioteca(botao) {
        var painel = document.querySelector('[data-library-panel]');
        var lugar = botao.closest('[data-library-wrap]');
        if (lugar && painel.parentNode !== lugar) lugar.appendChild(painel);
        depoisDe = botao.closest('[data-row="clause"]');
        painel.classList.remove('hidden');
        painel.innerHTML = '<p class="text-sm text-ink-2">Carregando…</p>';
        fetch(CT.urls.library, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (lista) {
                listaBiblioteca = lista;
                if (!lista.length) {
                    painel.innerHTML = '<p class="text-sm text-ink-2">A biblioteca do setor está vazia. Use "Salvar na biblioteca" numa cláusula.</p>';
                    return;
                }
                painel.innerHTML = lista.map(function (c, i) {
                    return '<button type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-subtle" data-library-pick="'
                        + i + '"><b>' + escape(c.title) + '</b>'
                        + (c.category ? ' <span class="text-xs text-ink-3">' + escape(c.category) + '</span>' : '') + '</button>';
                }).join('');
            })
            .catch(function () {
                painel.innerHTML = '<p class="text-sm text-danger">Não foi possível carregar a biblioteca. Tente de novo.</p>';
            });
    }

    function insereDaBiblioteca(c) {
        var k = chave();
        var card = molde('tpl-clause', { '__K__': k });
        // A cláusula de referência pode ter sido removida com o painel aberto.
        insereClausula(card, depoisDe && depoisDe.isConnected ? depoisDe : null);
        depoisDe = null;
        card.querySelector('input[name$="[title]"]').value = c.title;
        (c.items || []).forEach(function (item) { novoItem(card, item.html, item.level); });
        if (!(c.items || []).length) novoItem(card);
        preencheSelects(card);
        renumera();
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        marca(true);
    }

    function salvaNaBiblioteca(card, botao) {
        var titulo = card.querySelector('input[name$="[title]"]').value.trim();
        if (!titulo) {
            alert('Dê um título à cláusula antes de salvá-la na biblioteca.');
            return;
        }
        var categoria = prompt('Categoria na biblioteca (opcional — ex.: Pagamento, Multa, Foro):', '');
        if (categoria === null) return;
        var itens = Array.prototype.map.call(card.querySelectorAll('[data-row="item"]'), function (item) {
            return { html: item.querySelector('.ct-rich').innerHTML, level: parseInt(item.querySelector('[data-level]').value, 10) || 1 };
        });
        fetch(CT.urls.libraryStore, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CT.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ sector_id: CT.sectorId, title: titulo, category: categoria, items: itens })
        }).then(function (r) {
            botao.textContent = r.ok ? 'Salva na biblioteca ✓' : 'Não salvou — confira o texto';
        });
    }

    // ---- Cadastro de contratadas ----
    var buscaEspera = null;
    function buscaParte(campo) {
        clearTimeout(buscaEspera);
        var caixa = campo.closest('[data-row="party"]').querySelector('[data-party-results]');
        var termo = campo.value.trim();
        if (termo.length < 2) {
            caixa.innerHTML = '';
            return;
        }
        buscaEspera = setTimeout(function () {
            fetch(CT.urls.parties + '?q=' + encodeURIComponent(termo), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (lista) {
                    listaPartes = lista;
                    caixa.innerHTML = lista.length
                        ? lista.map(function (p, i) {
                            return '<button type="button" class="block w-full rounded-lg bg-surface px-3 py-1.5 text-left text-sm hover:bg-line" data-party-pick="'
                                + i + '">' + escape(p.name) + ' <span class="text-xs text-ink-3">' + escape(p.document) + '</span></button>';
                        }).join('')
                        : '<p class="px-1 text-xs text-ink-3">Nada no cadastro. Preencha abaixo: ao salvar, fica guardado.</p>';
                });
        }, 350);
    }

    function preencheParte(linha, p) {
        ['name', 'document', 'address', 'details', 'rep_name', 'rep_cpf', 'email', 'phone', 'gender', 'kind'].forEach(function (campo) {
            var el = linha.querySelector('[name$="[' + campo + ']"]');
            if (el && p[campo] != null) el.value = p[campo];
        });
        linha.querySelectorAll('textarea[data-autogrow]').forEach(cresce);
        var tipo = linha.querySelector('[data-party-kind]');
        tipo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    preencheSelects();
    renumera();
    desenhaFaltaPreencher();
})();
</script>
