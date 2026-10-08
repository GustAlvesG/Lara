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

    var atual = 0;
    // Até onde a pessoa já chegou: dali para trás ela circula à vontade.
    var alcancado = erros.length ? passos.length - 1 : 0;

    function botao(texto, classes) {
        var b = document.createElement('button');
        b.type = 'button';
        b.textContent = texto;
        b.className = classes;

        return b;
    }

    var trilha = document.createElement('div');
    trilha.className = 'flex flex-wrap items-center gap-2';

    if (passos[0].parentNode === form) {
        trilha.style.marginBottom = '1.5rem';
    }

    var marcas = passos.map(function (passo, i) {
        var marca = botao((i + 1) + '. ' + passo.getAttribute('data-step'), '');

        marca.addEventListener('click', function () { irPara(i); });
        trilha.appendChild(marca);

        return marca;
    });

    passos[0].parentNode.insertBefore(trilha, passos[0]);

    var voltar = botao('Voltar', 'px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition');
    var continuar = botao('Continuar', 'px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition');

    acoes.insertBefore(voltar, enviar);
    acoes.insertBefore(continuar, enviar);

    function mostra(i) {
        atual = i;
        alcancado = Math.max(alcancado, i);

        passos.forEach(function (passo, n) {
            passo.style.display = n === i ? '' : 'none';
        });

        marcas.forEach(function (marca, n) {
            marca.className = 'px-5 py-2.5 rounded-full font-bold text-sm transition '
                + (n === i
                    ? 'bg-grena text-white'
                    : (livre || n <= alcancado ? 'bg-subtle text-ink hover:bg-line' : 'bg-subtle text-ink-3'));

            if (n === i) {
                marca.setAttribute('aria-current', 'step');
            } else {
                marca.removeAttribute('aria-current');
            }
        });

        var ultimo = i === passos.length - 1;

        voltar.style.display = i === 0 ? 'none' : '';
        continuar.style.display = ultimo ? 'none' : '';

        if (enviar && !livre) {
            enviar.style.display = ultimo ? '' : 'none';
        }
    }

    // O primeiro campo do passo que o navegador recusa (obrigatório vazio, e-mail torto).
    function campoInvalido(i) {
        var campos = passos[i].querySelectorAll('input, select, textarea');

        for (var n = 0; n < campos.length; n++) {
            if (!campos[n].checkValidity()) {
                return campos[n];
            }
        }

        return null;
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
        trilha.scrollIntoView({ block: 'start' });
    }

    voltar.addEventListener('click', function () { irPara(atual - 1); });
    continuar.addEventListener('click', function () { irPara(atual + 1); });

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

        if (!livre && atual < passos.length - 1) {
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

    // Depois de um envio recusado pelo servidor, abre no primeiro passo com erro.
    var inicial = 0;

    passos.some(function (passo, i) {
        var chaves = (passo.getAttribute('data-step-keys') || '').split(',').filter(Boolean);

        var comErro = erros.some(function (erro) {
            return chaves.some(function (chave) {
                return erro === chave || erro.indexOf(chave + '.') === 0;
            });
        });

        if (comErro) {
            inicial = i;
        }

        return comErro;
    });

    mostra(inicial);
    }
})();
</script>
