@php
    /**
     * O formulário do documento em PASSOS, um cartão por vez.
     *
     * Cada cartão marcado com `data-step="Nome do passo"` vira um passo; a
     * linha de botões marcada com `data-step-actions` ganha "Voltar" e
     * "Continuar", e o botão de gravar só aparece no último passo.
     *
     * É só apresentação: o formulário continua um só, enviado de uma vez, e
     * quem confere é o servidor. Sem JavaScript, os cartões aparecem todos,
     * como antes.
     *
     * - `data-step-keys="title,signers"` diz que erros de validação são
     *   daquele passo: depois de um envio recusado, a tela abre nele.
     * - `data-steps-free` no <form> (edição do rascunho) libera ir a qualquer
     *   passo e gravar de qualquer um: ali tudo já foi preenchido uma vez.
     * - `data-step-start="N"` no <form> abre no passo N (contando de 0) quando
     *   não há erro; um `input[data-step-current]` recebe o passo aberto ao
     *   gravar, para a tela voltar nele (editor de contratos, 04/11/2026).
     *
     * Desde 11/11/2026 (UX do preenchimento, pedido do usuário): trilha de
     * progresso com o estado de cada passo (feito ✓, atual, com erro !,
     * ainda fechado), "Passo N de M" e barra; o selo "Passo N de M" no alto de
     * cada cartão; a linha de botões presa no pé da tela (`lara-step-actions`)
     * com "Continuar: <próximo passo>"; o foco vai ao 1º campo do passo aberto.
     * O desenho está em app.css ("Formulário em passos").
     */
@endphp
<script>
(function () {
    // Este script fica dentro do formulário, ANTES da linha de botões da tela:
    // só dá para montar os passos com a página inteira lida.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', monta);
    } else {
        monta();
    }

    function monta() {
    var primeiro = document.querySelector('[data-step]');
    var form = primeiro ? primeiro.closest('form') : null;

    if (!form) {
        return;
    }

    var passos = Array.prototype.slice.call(form.querySelectorAll('[data-step]'));
    var acoes = form.querySelector('[data-step-actions]');

    if (passos.length < 2 || !acoes) {
        return;
    }

    var enviar = acoes.querySelector('button[type="submit"]');
    var livre = form.hasAttribute('data-steps-free');
    var erros = @json($errors->keys());
    var total = passos.length;

    var atual = 0;
    // Até onde a pessoa já chegou: dali para trás ela circula à vontade.
    var alcancado = erros.length ? total - 1 : 0;

    function botao(texto, classes) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = texto;
        b.className = classes;

        return b;
    }

    function nome(i) {
        return passos[i].getAttribute('data-step');
    }

    // Passos com erro vindo do servidor (data-step-keys).
    var comErroServidor = passos.map(function (passo) {
        var chaves = (passo.getAttribute('data-step-keys') || '').split(',').filter(Boolean);

        return erros.some(function (erro) {
            return chaves.some(function (chave) {
                return erro === chave || erro.indexOf(chave + '.') === 0;
            });
        });
    });

    // ---- Trilha: "Passo N de M · Nome", os pontos e a barra. ----
    var trilha = document.createElement('nav');
    trilha.className = 'lara-stepper';
    trilha.setAttribute('aria-label', 'Passos do formulário');

    if (passos[0].parentNode === form) {
        trilha.style.marginBottom = '1.25rem';
    }

    var contagem = document.createElement('p');
    contagem.className = 'lara-stepper-count';
    contagem.setAttribute('aria-live', 'polite');

    var lista = document.createElement('ol');
    var barra = document.createElement('div');
    barra.className = 'lara-stepper-bar';
    barra.setAttribute('aria-hidden', 'true');
    var enchimento = document.createElement('span');
    barra.appendChild(enchimento);

    var marcas = passos.map(function (passo, i) {
        var item = document.createElement('li');
        var marca = document.createElement('button');
        marca.type = 'button';
        marca.innerHTML = '<span class="lara-stepper-dot" aria-hidden="true"></span><span class="lara-stepper-name"></span>';
        marca.querySelector('.lara-stepper-name').textContent = nome(i);
        marca.addEventListener('click', function () { irPara(i); });
        item.appendChild(marca);
        lista.appendChild(item);

        // Selo no alto do cartão: onde a pessoa está, sem depender da trilha.
        var selo = document.createElement('p');
        selo.className = 'lara-step-tag';
        selo.textContent = 'Passo ' + (i + 1) + ' de ' + total + ' · ' + nome(i);
        passo.insertBefore(selo, passo.firstChild);

        return marca;
    });

    trilha.appendChild(contagem);
    trilha.appendChild(lista);
    trilha.appendChild(barra);
    passos[0].parentNode.insertBefore(trilha, passos[0]);

    // ---- Botões: presos no pé da tela, com o nome do próximo passo. ----
    acoes.classList.add('lara-step-actions');

    var voltar = botao('Voltar', 'lara-step-back');
    var continuar = botao('Continuar', 'lara-step-next');

    acoes.insertBefore(voltar, enviar);
    acoes.insertBefore(continuar, enviar);

    // O primeiro campo do passo que o navegador recusa (obrigatório vazio, e-mail torto).
    function campoInvalido(i) {
        var campos = passos[i].querySelectorAll('input, select, textarea');

        for (var n = 0; n < campos.length; n++) {
            if (!campos[n].disabled && !campos[n].checkValidity()) {
                return campos[n];
            }
        }

        return null;
    }

    function estado(n) {
        if (n === atual) {
            return 'current';
        }
        if (comErroServidor[n]) {
            return 'error';
        }
        if (n <= alcancado || livre) {
            return n < atual || n <= alcancado ? (campoInvalido(n) ? 'todo' : 'done') : 'todo';
        }

        return 'locked';
    }

    function pinta() {
        marcas.forEach(function (marca, n) {
            var s = estado(n);
            marca.setAttribute('data-state', s);
            marca.querySelector('.lara-stepper-dot').textContent = s === 'done' ? '✓' : (s === 'error' ? '!' : String(n + 1));
            marca.setAttribute('aria-label', 'Passo ' + (n + 1) + ': ' + nome(n)
                + (s === 'done' ? ' (preenchido)' : s === 'error' ? ' (com erro)' : ''));

            if (n === atual) {
                marca.setAttribute('aria-current', 'step');
            } else {
                marca.removeAttribute('aria-current');
            }
        });

        contagem.innerHTML = 'Passo <b>' + (atual + 1) + '</b> de ' + total + ' · <span></span>';
        contagem.querySelector('span').textContent = nome(atual);
        enchimento.style.width = (total > 1 ? (atual / (total - 1)) * 100 : 100) + '%';
    }

    function mostra(i) {
        atual = i;
        alcancado = Math.max(alcancado, i);

        passos.forEach(function (passo, n) {
            passo.style.display = n === i ? '' : 'none';
        });

        var ultimo = i === total - 1;

        // Quem mede algo no passo (caixa que cresce com o texto) mede de novo agora que ele aparece.
        passos[i].dispatchEvent(new CustomEvent('step:shown', { bubbles: true }));

        voltar.style.display = i === 0 ? 'none' : '';
        continuar.style.display = ultimo ? 'none' : '';
        continuar.textContent = ultimo ? 'Continuar' : 'Continuar: ' + nome(i + 1);

        if (enviar && !livre) {
            enviar.style.display = ultimo ? '' : 'none';
        }

        pinta();
    }

    // O foco vai ao 1º campo visível do passo aberto (sem rolar a tela de novo).
    function focaPasso(i) {
        var campo = passos[i].querySelector('input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])');

        if (campo && campo.offsetParent !== null) {
            try { campo.focus({ preventScroll: true }); } catch (e) { campo.focus(); }
        }
    }

    /*
     * Para trás, sempre. Para a frente, só passando por cada passo do caminho
     * com os campos em ordem — é o que impede chegar ao fim com um signatário
     * sem nome e descobrir isso só ao gravar.
     */
    function irPara(destino) {
        if (!livre) {
            for (var i = atual; i < destino; i++) {
                var campo = campoInvalido(i);

                if (campo) {
                    mostra(i);
                    campo.reportValidity();

                    return;
                }
            }
        }

        mostra(destino);
        trilha.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        focaPasso(destino);
    }

    voltar.addEventListener('click', function () { irPara(atual - 1); });
    continuar.addEventListener('click', function () { irPara(atual + 1); });

    // Ao preencher, o passo atual e os já vistos ganham ✓ assim que ficam completos.
    var pendente = false;
    form.addEventListener('input', function (event) {
        // Mexeu no passo que voltou com erro do servidor: o "!" sai.
        var n = passos.indexOf(event.target.closest('[data-step]'));
        if (n !== -1) {
            comErroServidor[n] = false;
        }

        if (pendente) {
            return;
        }
        pendente = true;
        requestAnimationFrame(function () { pendente = false; pinta(); });
    });

    // Enter num campo enviaria o formulário pela metade: aqui ele avança o passo.
    form.addEventListener('keydown', function (event) {
        var alvo = event.target;

        if (event.key !== 'Enter' || alvo.tagName !== 'INPUT' || ['submit', 'button', 'file'].indexOf(alvo.type) !== -1) {
            return;
        }

        if (alvo.matches('[data-member-search]')) {
            event.preventDefault();

            return;
        }

        if (!livre && atual < total - 1) {
            event.preventDefault();
            irPara(atual + 1);
        }
    });

    /*
     * Campo recusado num passo que não está na tela (ao gravar): abre o passo
     * dele, senão o navegador reclama de um campo que ninguém vê. Com vários
     * recusados de uma vez, vale o primeiro.
     */
    var tratando = false;

    form.addEventListener('invalid', function (event) {
        if (tratando) {
            return;
        }

        tratando = true;
        setTimeout(function () { tratando = false; }, 0);

        var i = passos.indexOf(event.target.closest('[data-step]'));

        if (i !== -1 && i !== atual) {
            mostra(i);
        }
    }, true);

    var passoAtual = form.querySelector('input[data-step-current]');

    if (passoAtual) {
        form.addEventListener('submit', function () { passoAtual.value = atual; });
    }

    // Depois de um envio recusado pelo servidor, abre no primeiro passo com erro.
    var pedido = parseInt(form.getAttribute('data-step-start') || '0', 10);
    var inicial = pedido > 0 && pedido < total ? pedido : 0;
    var primeiroComErro = comErroServidor.indexOf(true);

    if (primeiroComErro !== -1) {
        inicial = primeiroComErro;
    }

    mostra(inicial);
    }
})();
</script>
