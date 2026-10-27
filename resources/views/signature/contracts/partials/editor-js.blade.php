{{--
    Comportamento do editor do contrato. Sem lógica de contrato aqui: a
    numeração, as variáveis e os problemas vêm da prévia do servidor
    (ContractController::preview). O script só monta linhas, move, sincroniza
    o texto rico com o textarea e pede a prévia.
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

    function chave() { return 'n' + Math.random().toString(36).slice(2, 11); }

    function escape(texto) {
        var d = document.createElement('div');
        d.textContent = texto == null ? '' : String(texto);
        return d.innerHTML;
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

        var add = e.target.closest('[data-add]');
        if (add) {
            var tipo = add.dataset.add;
            var k = chave();
            var linha = molde('tpl-' + tipo, { '__K__': k });
            document.querySelector('[data-list="' + tipo + '"]').appendChild(linha);
            if (tipo === 'clause') novoItem(linha);
            preencheSelects(linha);
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
            remover.closest('.ct-row').remove();
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
            abreBiblioteca();
            return;
        }

        var daBiblioteca = e.target.closest('[data-library-pick]');
        if (daBiblioteca) {
            insereDaBiblioteca(JSON.parse(daBiblioteca.dataset.libraryPick));
            document.querySelector('[data-library-panel]').classList.add('hidden');
            return;
        }

        var cadastro = e.target.closest('[data-party-pick]');
        if (cadastro) {
            preencheParte(cadastro.closest('[data-row="party"]'), JSON.parse(cadastro.dataset.partyPick));
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
                diretor.value ? opcao.textContent.replace(/\s*\(.*$/, '').trim() : '';
        }

        marca(true);
    });

    form.addEventListener('input', function (e) {
        if (e.target.closest('[data-party-search]')) {
            buscaParte(e.target);
            return;
        }
        marca(true);
    });

    form.addEventListener('submit', function () {
        sincroniza();
        alterado = false;
    });

    window.addEventListener('beforeunload', function (e) {
        if (alterado) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---- Prévia (servidor) ----
    var espera = null;
    function marca(previa) {
        alterado = true;
        if (estado) estado.textContent = 'Alterações não salvas.';
        if (previa !== false) {
            clearTimeout(espera);
            espera = setTimeout(atualizaPrevia, 1500);
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
            preencheSelects();
            desenhaProblemas(j.problems);
        });
    }

    function desenhaProblemas(lista) {
        if (!problemsBox) return;
        var erros = lista.filter(function (p) { return p.level === 'error'; });
        var avisos = lista.filter(function (p) { return p.level === 'warning'; });
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
    function abreBiblioteca() {
        var painel = document.querySelector('[data-library-panel]');
        painel.classList.remove('hidden');
        painel.innerHTML = '<p class="text-sm text-ink-2">Carregando…</p>';
        fetch(CT.urls.library, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (lista) {
                if (!lista.length) {
                    painel.innerHTML = '<p class="text-sm text-ink-2">A biblioteca do setor está vazia. Use "Salvar na biblioteca" numa cláusula.</p>';
                    return;
                }
                painel.innerHTML = lista.map(function (c) {
                    return '<button type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-subtle" data-library-pick="'
                        + escape(JSON.stringify(c)) + '"><b>' + escape(c.title) + '</b>'
                        + (c.category ? ' <span class="text-xs text-ink-3">' + escape(c.category) + '</span>' : '') + '</button>';
                }).join('');
            });
    }

    function insereDaBiblioteca(c) {
        var k = chave();
        var card = molde('tpl-clause', { '__K__': k });
        document.querySelector('[data-list="clause"]').appendChild(card);
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
                    caixa.innerHTML = lista.length
                        ? lista.map(function (p) {
                            return '<button type="button" class="block w-full rounded-lg bg-surface px-3 py-1.5 text-left text-sm hover:bg-line" data-party-pick="'
                                + escape(JSON.stringify(p)) + '">' + escape(p.name) + ' <span class="text-xs text-ink-3">' + escape(p.document) + '</span></button>';
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
        var tipo = linha.querySelector('[data-party-kind]');
        tipo.dispatchEvent(new Event('change', { bubbles: true }));
    }

    preencheSelects();
    renumera();
})();
</script>
