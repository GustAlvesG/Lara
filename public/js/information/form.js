/**
 * Componente Alpine do formulário do InfoClube (criar e editar).
 *
 * Substitui o optionalFields.js, que clonava linhas com jQuery e alternava
 * visibilidade por classe. Aquele esquema tinha dois defeitos estruturais:
 * as classes de seção (.responsible, .day_hour, .prices) também estavam nos
 * inputs internos, então esconder a seção escondia campos que deveriam
 * continuar visíveis; e as linhas clonadas herdavam ids duplicados.
 *
 * Aqui cada bloco repetível é um array e as seções desligadas saem do DOM
 * (x-if), então nada é enviado para o backend quando o campo está desativado.
 * A ordem dos inputs no DOM é a ordem do array, o que mantém name_price[],
 * price_associated[] e price_not_associated[] alinhados entre si — que é
 * exatamente o que o controller espera ao juntar tudo com ";".
 */
window.informationForm = function (initial) {
    var uid = 0;

    function withIds(rows, blank) {
        return (rows || []).map(function (row) {
            return Object.assign({ _id: ++uid }, blank, row);
        });
    }

    return {
        // Cada chave liga/desliga uma seção do formulário.
        toggles: Object.assign(
            {
                image: false,
                fee: false,
                prices: false,
                schedules: false,
                responsibles: false,
                slots: false,
                status: false,
                location: false,
            },
            initial.toggles || {}
        ),

        prices: withIds(initial.prices, { name: '', associated: '', not_associated: '' }),
        responsibles: withIds(initial.responsibles, { name: '', contact: '' }),
        schedules: withIds(initial.schedules, { day: '', start: '', end: '' }),

        dayOptions: [
            'Domingo',
            'Segunda-feira',
            'Terça-feira',
            'Quarta-feira',
            'Quinta-feira',
            'Sexta-feira',
            'Sábado',
            'Dias de Semana',
            'Fim de Semana',
            'Todos os dias',
        ],

        hasImage: !!initial.hasImage,
        removeImage: false,

        // Título espelhado da coluna da esquerda: alimenta o texto do
        // placeholder da imagem enquanto o usuário digita.
        title: initial.title || '',

        // URL da imagem já salva; vira object URL ao escolher um arquivo novo.
        imageUrl: initial.imageUrl || null,

        tags: (initial.tags || []).slice(),
        tagDraft: '',

        minTags: initial.minTags || 3,

        init: function () {
            // Uma seção ligada sem nenhuma linha ficaria vazia e sem forma de
            // preencher — garante a primeira linha.
            this.ensureRow('prices');
            this.ensureRow('responsibles');
            this.ensureRow('schedules');
        },

        /* --- Imagem (exibição na coluna da esquerda) --- */

        placeholderUrl: function () {
            var text = (this.title || 'InfoClube').slice(0, 60);
            return 'https://placehold.co/600x400/7d0400/ffffff?text=' + encodeURIComponent(text);
        },

        previewUrl: function () {
            if (this.removeImage || !this.imageUrl) {
                return this.placeholderUrl();
            }
            return this.imageUrl;
        },

        onImagePicked: function (event) {
            var file = event.target.files && event.target.files[0];
            if (file) {
                this.imageUrl = URL.createObjectURL(file);
                this.removeImage = false;
            }
        },

        /* --- Tags --- */

        addTag: function () {
            var name = this.tagDraft.trim().toLowerCase();
            this.tagDraft = '';

            if (name === '') {
                return;
            }

            if (!this.tags.includes(name)) {
                this.tags.push(name.slice(0, 50));
            }
        },

        removeTag: function (index) {
            this.tags.splice(index, 1);
        },

        removeLastTag: function () {
            if (this.tags.length) {
                this.tags.pop();
            }
        },

        tagsMissing: function () {
            return Math.max(0, this.minTags - this.tags.length);
        },

        ensureRow: function (key) {
            if (this.toggles[key] && this[key].length === 0) {
                this.addRow(key);
            }
        },

        addRow: function (key) {
            if (key === 'prices') {
                this.prices.push({ _id: ++uid, name: '', associated: '', not_associated: '' });
            } else if (key === 'responsibles') {
                this.responsibles.push({ _id: ++uid, name: '', contact: '' });
            } else if (key === 'schedules') {
                this.schedules.push({ _id: ++uid, day: '', start: '', end: '' });
            }
        },

        removeRow: function (key, index) {
            this[key].splice(index, 1);
        },

        /**
         * Move uma linha para cima (-1) ou para baixo (+1).
         *
         * A ordem do array é a ordem dos inputs no DOM, que é a ordem em que os
         * valores são concatenados com ";" no controller. Ou seja: reordenar
         * aqui muda de fato qual item fica em primeiro no banco — e é o
         * primeiro que o card da listagem exibe (preço e contato do WhatsApp).
         */
        moveRow: function (key, index, direction) {
            var target = index + direction;

            if (target < 0 || target >= this[key].length) {
                return;
            }

            var moved = this[key].splice(index, 1)[0];
            this[key].splice(target, 0, moved);
        },

        /**
         * Chamado no @change do switch da seção. Ligar cria a primeira linha,
         * desligar zera as linhas para não reenviar dados de um campo que o
         * usuário acabou de desativar.
         */
        onToggle: function (key) {
            if (!['prices', 'responsibles', 'schedules'].includes(key)) {
                return;
            }

            if (this.toggles[key]) {
                this.ensureRow(key);
            } else {
                this[key] = [];
            }
        },

        /**
         * Registros antigos foram gravados com abreviações ("2ª", "Dom") que
         * não existem mais na lista atual. Mantém o valor salvo como opção
         * própria para a edição não trocar silenciosamente o dia.
         */
        legacyDay: function (value) {
            return value && value !== '#' && !this.dayOptions.includes(value);
        },
    };
};
