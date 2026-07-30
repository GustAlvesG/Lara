/**
 * Editor de texto rico próprio do InfoClube (substitui o CKEditor).
 *
 * Cada `[data-rich-editor]` é uma div contenteditable + uma textarea escondida
 * com o mesmo `name` do campo original (ex: "description"). O conteúdo do
 * contenteditable é copiado pra textarea a cada edição e no submit do form,
 * então o backend continua recebendo o campo exatamente como antes — nada
 * muda no InformationController além do HTML que passa a vir de um editor
 * mais simples (negrito, itálico, sublinhado, tabela, cor de fundo).
 */
(function () {
    function syncSource(root) {
        var content = root.querySelector('[data-rich-editor-content]');
        var source = root.querySelector('.rich-editor-source');
        if (content && source) {
            source.value = content.innerHTML.trim();
        }
    }

    function updateToolbarState(root) {
        root.querySelectorAll('[data-cmd]').forEach(function (btn) {
            var active = false;
            try {
                active = document.queryCommandState(btn.dataset.cmd);
            } catch (e) {
                active = false;
            }
            btn.classList.toggle('is-active', !!active);
        });
    }

    function applyBackgroundColor(color) {
        if (!document.execCommand('hiliteColor', false, color)) {
            document.execCommand('backColor', false, color);
        }
    }

    function buildTableHtml(rows, cols) {
        var html = '<table><tbody>';
        for (var r = 0; r < rows; r++) {
            html += '<tr>';
            for (var c = 0; c < cols; c++) {
                html += '<td>&nbsp;</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table><p><br></p>';
        return html;
    }

    function closeAnyPopover() {
        document.querySelectorAll('.rich-editor-popover').forEach(function (el) {
            el.remove();
        });
    }

    function openTablePopover(anchorBtn, onConfirm) {
        closeAnyPopover();

        var popover = document.createElement('div');
        popover.className = 'rich-editor-popover';
        popover.innerHTML =
            '<label>Linhas<input type="number" min="1" max="20" value="2" data-rows></label>' +
            '<label>Colunas<input type="number" min="1" max="10" value="2" data-cols></label>' +
            '<button type="button" data-confirm>Inserir</button>';

        anchorBtn.parentElement.style.position = 'relative';
        anchorBtn.parentElement.appendChild(popover);

        popover.querySelector('[data-confirm]').addEventListener('click', function () {
            var rows = parseInt(popover.querySelector('[data-rows]').value, 10) || 1;
            var cols = parseInt(popover.querySelector('[data-cols]').value, 10) || 1;
            popover.remove();
            onConfirm(rows, cols);
        });

        setTimeout(function () {
            document.addEventListener('click', function handler(event) {
                if (!popover.contains(event.target) && event.target !== anchorBtn) {
                    popover.remove();
                    document.removeEventListener('click', handler);
                }
            });
        });
    }

    function initEditor(root) {
        var content = root.querySelector('[data-rich-editor-content]');
        var source = root.querySelector('.rich-editor-source');
        if (!content || !source) {
            return;
        }

        try {
            document.execCommand('styleWithCSS', false, true);
        } catch (e) {
            // Navegador sem suporte a styleWithCSS: hiliteColor ainda funciona,
            // só cai pro estilo legado.
        }

        root.querySelectorAll('[data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                content.focus();
                document.execCommand(btn.dataset.cmd, false, null);
                syncSource(root);
                updateToolbarState(root);
            });
        });

        var tableBtn = root.querySelector('[data-action="table"]');
        if (tableBtn) {
            tableBtn.addEventListener('click', function () {
                openTablePopover(tableBtn, function (rows, cols) {
                    content.focus();
                    document.execCommand('insertHTML', false, buildTableHtml(rows, cols));
                    syncSource(root);
                });
            });
        }

        var colorInput = root.querySelector('[data-action="bgcolor"]');
        if (colorInput) {
            colorInput.addEventListener('input', function () {
                content.focus();
                applyBackgroundColor(colorInput.value);
                syncSource(root);
            });
        }

        var clearColorBtn = root.querySelector('[data-action="clear-bgcolor"]');
        if (clearColorBtn) {
            clearColorBtn.addEventListener('click', function () {
                content.focus();
                applyBackgroundColor('transparent');
                syncSource(root);
            });
        }

        content.addEventListener('input', function () {
            syncSource(root);
        });
        content.addEventListener('keyup', function () {
            updateToolbarState(root);
        });
        content.addEventListener('mouseup', function () {
            updateToolbarState(root);
        });

        var form = root.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                syncSource(root);
            });
        }

        syncSource(root);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rich-editor]').forEach(initEditor);
    });
})();
