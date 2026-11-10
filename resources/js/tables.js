/*
 | Tabelas das telas (11/11/2026). O desenho está em resources/css/app.css
 | ("Tabelas"); aqui só o que precisa do DOM:
 |
 | - data-label em cada célula, com o título da coluna: no celular a linha
 |   vira um cartão "rótulo: valor" e nenhuma coluna fica escondida;
 | - data-stack só em tabela simples (uma linha de cabeçalho, sem rowspan,
 |   toda linha com uma célula por coluna ou uma só célula de largura toda);
 | - data-scroll no contêiner que rola: diz se ainda há colunas à direita
 |   (a 1ª coluna fica presa e a borda esmaece).
 |
 | Roda de novo quando o Alpine troca linhas (MutationObserver). Tabela que
 | nunca deve virar cartão: data-table="scroll".
 */
const SELECTOR = 'table:is(.w-full, .min-w-full, .text-sm, .text-xs, .text-left)';
const ACTION_LABELS = ['', 'ações', 'ação', 'acoes', 'acao'];

const labelOf = (cell) =>
    (cell.textContent || '').replace(/\s+/g, ' ').trim() ||
    cell.getAttribute('aria-label') ||
    cell.querySelector('[aria-label]')?.getAttribute('aria-label') ||
    '';

function headerLabels(table) {
    const rows = table.tHead?.rows;
    if (!rows || rows.length !== 1) return null;
    const cells = Array.from(rows[0].cells);
    if (cells.some((c) => c.colSpan > 1 || c.rowSpan > 1)) return null;
    return cells.map(labelOf);
}

function stack(table, labels) {
    const n = labels.length;
    if (n < 2 || table.dataset.table === 'scroll') return false;

    const rows = Array.from(table.tBodies).flatMap((b) => Array.from(b.rows));
    const fits = rows.every((row) => {
        const cells = Array.from(row.cells);
        if (cells.some((c) => c.rowSpan > 1)) return false;
        return cells.length === 1 || (cells.length === n && cells.every((c) => c.colSpan === 1));
    });
    if (!fits) return false;

    const firstIsTitle = labels[0] !== '';
    const last = n - 1;
    const lastIsActions = ACTION_LABELS.includes(labels[last].toLowerCase());

    for (const row of rows) {
        const cells = Array.from(row.cells);
        if (cells.length !== n) continue;
        cells.forEach((cell, i) => {
            if (cell.dataset.label !== labels[i]) cell.dataset.label = labels[i];
        });
        const first = cells[0];
        if (firstIsTitle && !first.querySelector('input[type="checkbox"], input[type="radio"]')) {
            first.dataset.title = '';
        }
        if (lastIsActions) cells[last].dataset.actions = '';
    }
    return true;
}

function scroller(table) {
    const parent = table.parentElement;
    if (!parent) return null;
    const overflow = getComputedStyle(parent).overflowX;
    if (overflow === 'auto' || overflow === 'scroll') return parent;

    const wrap = document.createElement('div');
    wrap.className = 'lara-table-scroll';
    parent.insertBefore(wrap, table);
    wrap.appendChild(table);
    return wrap;
}

function track(box) {
    if (box.dataset.scrollTracked) return;
    box.dataset.scrollTracked = '1';

    const update = () => {
        const max = box.scrollWidth - box.clientWidth;
        let state = 'none';
        if (max > 2) state = box.scrollLeft <= 2 ? 'start' : box.scrollLeft >= max - 2 ? 'end' : 'mid';
        if (box.dataset.scroll !== state) box.dataset.scroll = state;
    };
    box.addEventListener('scroll', update, { passive: true });
    if ('ResizeObserver' in window) new ResizeObserver(update).observe(box);
    update();
}

/*
 | Linha clicável (11/11/2026, pedido do usuário): clicar em qualquer lugar da
 | linha faz o mesmo que o primeiro link dela (o ID, o nome) ou, sem link, o
 | primeiro botão. Por segurança, nunca vale para:
 | - botão que envia formulário, botão desabilitado ou de excluir, cancelar,
 |   remover etc. (link também, pelo texto);
 | - mailto:, tel:, WhatsApp e "#";
 | - linha com campo de formulário (edição em lote), ou que já tem clique
 |   próprio (@click/onclick).
 | Clique em link, botão, campo ou elemento com clique próprio segue normal;
 | texto selecionado não navega. Ctrl/⌘ ou botão do meio abre em outra aba.
 | O teclado continua pelo próprio link (sem tabindex extra na linha).
 */
const DANGER = /excluir|apagar|remover|deletar|cancelar|desativar|inativar|recusar|bloquear|revogar|estornar|delete|remove/i;
const OWN_CLICK = /^(@click|x-on:click|onclick)/;
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, details, [role="button"], [role="link"], [contenteditable]';

const hasOwnClick = (el) => Array.from(el.attributes).some((a) => OWN_CLICK.test(a.name));
const visible = (el) => el.getClientRects().length > 0;
const textOf = (el) => `${el.textContent || ''} ${el.getAttribute('aria-label') || ''} ${el.getAttribute('title') || ''}`;

function safeLink(a) {
    const href = (a.getAttribute('href') || '').trim();
    if (!href || href.startsWith('#') || /^(javascript|mailto|tel):/i.test(href) || /wa\.me|whatsapp/i.test(href)) return false;
    return !DANGER.test(textOf(a));
}

function safeButton(b) {
    if (b.disabled || (b.type === 'submit' && b.form)) return false;
    if (b.closest('form')?.querySelector('input[name="_method"][value="DELETE" i]')) return false;
    return !DANGER.test(textOf(b));
}

function rowTarget(row, needVisible = true) {
    const ok = (el) => !needVisible || visible(el);
    const link = Array.from(row.querySelectorAll('a[href]')).find((a) => safeLink(a) && ok(a));
    if (link) return link;
    return Array.from(row.querySelectorAll('button')).find((b) => safeButton(b) && ok(b)) || null;
}

function markRows(table) {
    for (const body of table.tBodies) {
        for (const row of body.rows) {
            const clickable = !hasOwnClick(row) &&
                !row.querySelector('input:not([type="hidden"]), select, textarea') &&
                rowTarget(row, false) !== null;
            if (clickable && !('rowLink' in row.dataset)) row.dataset.rowLink = '';
            else if (!clickable && 'rowLink' in row.dataset) delete row.dataset.rowLink;
        }
    }
}

function onRowClick(event) {
    if (event.defaultPrevented || (event.type === 'auxclick' && event.button !== 1)) return;
    const row = event.target.closest('tr[data-row-link]');
    if (!row) return;

    // Clique em algo que já faz alguma coisa: segue o normal.
    for (let el = event.target; el && el !== row; el = el.parentElement) {
        if (el.matches(INTERACTIVE) || hasOwnClick(el)) return;
    }
    if (String(window.getSelection?.() || '').trim() !== '') return;

    const target = rowTarget(row);
    if (!target) return;

    const newTab = event.type === 'auxclick' || event.ctrlKey || event.metaKey;
    if (newTab && target.tagName === 'A') {
        window.open(target.href, '_blank', 'noopener');
        return;
    }
    target.click();
}

function enhance(root) {
    root.querySelectorAll(SELECTOR).forEach((table) => {
        markRows(table);

        const labels = headerLabels(table);
        if (labels && stack(table, labels)) {
            if (!('stack' in table.dataset)) table.dataset.stack = '';
        } else if ('stack' in table.dataset) {
            delete table.dataset.stack;
        }

        const box = scroller(table);
        if (box) track(box);
    });
}

function start() {
    const root = document.querySelector('.lara-tables');
    if (!root) return;
    enhance(root);
    root.addEventListener('click', onRowClick);
    root.addEventListener('auxclick', onRowClick);

    // Linhas trocadas pelo Alpine (filtros, x-for): rotula de novo. Só
    // childList: os data-* gravados aqui não disparam outra rodada.
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            enhance(root);
        });
    }).observe(root, { childList: true, subtree: true });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();
