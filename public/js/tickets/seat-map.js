/*
 * Mapa de lugares dos Ingressos (mesas, bistrôs, camarotes) no Lara.
 *
 *   TicketSeatMap.viewer(container, opts)  — mostra o mapa com a situação dos
 *     lugares (emissão: escolher um lugar livre; ocupação: ver quem está em
 *     cada um). Busca o JSON em `opts.url` e recarrega a cada `refresh_after`.
 *   TicketSeatMap.editor(root, layout)     — desenha o mapa: lugares (clicar,
 *     arrastar, grade) e áreas de orientação (palco, piscina, banheiros).
 *
 * SVG puro, sem biblioteca. As posições são nas unidades do mapa
 * (`width` × `height`); o lugar guarda o centro (x, y) e o tamanho (w, h); a
 * área, o canto superior esquerdo e o tamanho. As cores vêm dos tokens do
 * tema (`rgb(var(--ok))`), então o escuro acompanha.
 */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const tone = (name, alpha) => (alpha === undefined ? `rgb(var(--${name}))` : `rgb(var(--${name}) / ${alpha})`);

    const AREA_COLORS = {
        stage: ['grena', 'grena'],
        water: ['a-portaria-ink', 'a-portaria-ink'],
        green: ['ok', 'ok'],
        service: ['ink-3', 'ink-3'],
        warn: ['warn', 'warn'],
    };

    const STATUS_STYLE = {
        free: { fill: tone('ok-soft'), stroke: tone('ok'), text: tone('ok') },
        held: { fill: tone('warn-soft'), stroke: tone('warn'), text: tone('warn') },
        sold: { fill: tone('danger-soft'), stroke: tone('danger'), text: tone('danger') },
        unavailable: { fill: tone('subtle'), stroke: tone('line-strong'), text: tone('ink-3'), dash: '4 3' },
        selected: { fill: tone('grena'), stroke: tone('grena-hover'), text: '#fff' },
    };

    const STATUS_LABELS = {
        free: 'Livre',
        held: 'Reservado (aguardando pagamento)',
        sold: 'Vendido',
        unavailable: 'Indisponível',
    };

    // Cor por categoria no editor, para ver de relance quais mesas são de qual tipo.
    const CATEGORY_TONES = ['a-reservas', 'a-portaria', 'a-externos', 'a-freela', 'a-placar', 'a-lara', 'a-info', 'a-compras'];

    function node(tag, attrs, style) {
        const el = document.createElementNS(NS, tag);
        Object.entries(attrs || {}).forEach(([k, v]) => v !== null && v !== undefined && el.setAttribute(k, v));
        Object.assign(el.style, style || {});
        return el;
    }

    function categoryTone(category, categories) {
        if (!category) return null;
        const i = categories.indexOf(category);
        return CATEGORY_TONES[(i < 0 ? 0 : i) % CATEGORY_TONES.length];
    }

    /** Desenha fundo, áreas e lugares. `seatStyle(seat)` decide as cores; `seatTitle(seat)` o texto ao passar o mouse. */
    function draw(svg, layout, opts) {
        svg.replaceChildren();
        svg.setAttribute('viewBox', `0 0 ${layout.width} ${layout.height}`);
        svg.setAttribute('role', 'img');

        svg.appendChild(node('rect', { x: 0, y: 0, width: layout.width, height: layout.height, rx: 8 }, { fill: tone('surface'), stroke: tone('line') }));

        if (layout.background_url) {
            const img = node('image', { x: 0, y: 0, width: layout.width, height: layout.height, preserveAspectRatio: 'xMidYMid meet' });
            img.setAttributeNS('http://www.w3.org/1999/xlink', 'href', layout.background_url);
            img.setAttribute('href', layout.background_url);
            svg.appendChild(img);
        }

        (layout.areas || []).forEach((area, index) => {
            const [fill, stroke] = AREA_COLORS[area.color] || AREA_COLORS.service;
            const g = node('g', { 'data-area': index }, { cursor: opts.areaCursor || 'default' });
            const shape = area.shape === 'ellipse'
                ? node('ellipse', { cx: area.x + area.w / 2, cy: area.y + area.h / 2, rx: area.w / 2, ry: area.h / 2 })
                : node('rect', { x: area.x, y: area.y, width: area.w, height: area.h, rx: 6 });
            Object.assign(shape.style, { fill: tone(fill, 0.16), stroke: tone(stroke, 0.7), strokeWidth: 2 });
            if (opts.areaSelected && opts.areaSelected(index)) {
                Object.assign(shape.style, { stroke: tone('grena'), strokeWidth: 3, strokeDasharray: '6 4' });
            }
            g.appendChild(shape);
            if (area.label) {
                const size = Math.max(10, Math.min(28, area.h / 3, area.w / Math.max(4, area.label.length * 0.6)));
                const text = node('text', { x: area.x + area.w / 2, y: area.y + area.h / 2, 'text-anchor': 'middle', 'dominant-baseline': 'central' },
                    { fill: tone(stroke), fontSize: `${size}px`, fontWeight: 700, letterSpacing: '.04em', pointerEvents: 'none', textTransform: 'uppercase' });
                text.textContent = area.label;
                g.appendChild(text);
            }
            svg.appendChild(g);
        });

        (layout.seats || []).forEach((seat) => {
            const st = opts.seatStyle(seat);
            const g = node('g', { 'data-seat': seat.uid ?? seat.id, tabindex: st.focusable ? 0 : null }, { cursor: st.cursor || 'default' });
            const shape = seat.shape === 'circle'
                ? node('ellipse', { cx: seat.x, cy: seat.y, rx: seat.w / 2, ry: seat.h / 2 })
                : node('rect', { x: seat.x - seat.w / 2, y: seat.y - seat.h / 2, width: seat.w, height: seat.h, rx: seat.shape === 'square' ? 4 : 6 });
            Object.assign(shape.style, { fill: st.fill, stroke: st.stroke, strokeWidth: st.strokeWidth || 2, strokeDasharray: st.dash || '' });
            g.appendChild(shape);

            const size = Math.max(8, Math.min(18, Math.min(seat.w, seat.h) * 0.36));
            const text = node('text', { x: seat.x, y: seat.y, 'text-anchor': 'middle', 'dominant-baseline': 'central' },
                { fill: st.text, fontSize: `${size}px`, fontWeight: 700, pointerEvents: 'none' });
            text.textContent = seat.label;
            g.appendChild(text);

            if (st.badge) {
                const badge = node('text', { x: seat.x + seat.w / 2, y: seat.y - seat.h / 2, 'text-anchor': 'middle', 'dominant-baseline': 'central' },
                    { fill: tone('ink-2'), fontSize: '11px', pointerEvents: 'none' });
                badge.textContent = st.badge;
                g.appendChild(badge);
            }

            const title = node('title');
            title.textContent = opts.seatTitle ? opts.seatTitle(seat) : seat.label;
            g.appendChild(title);
            svg.appendChild(g);
        });
    }

    function seatDescription(seat) {
        return [seat.label, seat.places ? `${seat.places} lugar${seat.places > 1 ? 'es' : ''}` : null, seat.category].filter(Boolean).join(' · ');
    }

    /** Zoom: muda a largura do SVG dentro do contêiner com rolagem. */
    function zoomControls(root, svg) {
        let zoom = 1;
        const apply = () => { svg.style.width = `${Math.round(zoom * 100)}%`; };
        root.querySelectorAll('[data-zoom]').forEach((btn) => btn.addEventListener('click', () => {
            const step = btn.dataset.zoom;
            zoom = step === 'reset' ? 1 : Math.max(0.5, Math.min(4, zoom + (step === 'in' ? 0.25 : -0.25)));
            apply();
        }));
        apply();
    }

    /* ------------------------------------------------------------------ viewer */

    function viewer(container, opts) {
        const svg = container.querySelector('svg[data-map]');
        const status = container.querySelector('[data-map-status]');
        let data = null;
        let selected = opts.selectedId ? Number(opts.selectedId) : null;
        let timer = null;

        zoomControls(container, svg);

        const pickable = (seat) => opts.mode === 'pick'
            && (seat.id === opts.currentSeatId
                || (seat.status === 'free' && (!opts.typeId || seat.type_id === Number(opts.typeId))));

        const render = () => {
            const typeNames = Object.fromEntries((data.types || []).map((t) => [t.id, t.name]));
            draw(svg, data, {
                seatStyle: (seat) => {
                    const isSel = seat.id === selected;
                    const base = STATUS_STYLE[isSel ? 'selected' : (seat.id === opts.currentSeatId ? 'free' : seat.status)] || STATUS_STYLE.unavailable;
                    const clickable = pickable(seat) || (opts.mode === 'view' && seat.order_id && opts.orderUrl);
                    return { ...base, cursor: clickable ? 'pointer' : 'not-allowed', focusable: clickable, strokeWidth: isSel ? 3 : 2 };
                },
                seatTitle: (seat) => {
                    const parts = [seatDescription(seat), seat.type_id ? typeNames[seat.type_id] : null, STATUS_LABELS[seat.status]];
                    if (seat.holder_name) parts.push(`${seat.holder_name} (pedido #${seat.order_id})`);
                    if (seat.active === false) parts.push('desativado');
                    return parts.filter(Boolean).join(' · ');
                },
            });
            if (status) {
                const count = (s) => data.seats.filter((seat) => seat.status === s).length;
                status.textContent = `${count('free')} livre(s) · ${count('held')} reservado(s) · ${count('sold')} vendido(s) · atualizado às ${new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
            }
        };

        const load = () => fetch(opts.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
            .then((json) => {
                data = json;
                // O lugar escolhido foi pego por outra pessoa enquanto a tela estava aberta.
                const mine = data.seats.find((s) => s.id === selected);
                if (selected && mine && !pickable(mine) && opts.mode === 'pick') {
                    selected = null;
                    opts.onPick && opts.onPick(null, data, 'taken');
                }
                render();
            })
            .catch(() => { if (status) status.textContent = 'Não foi possível carregar o mapa agora.'; })
            .finally(() => {
                clearTimeout(timer);
                const wait = ((data && data.refresh_after) || 15) * 1000;
                timer = setTimeout(() => (document.hidden ? (timer = setTimeout(load, wait)) : load()), wait);
            });

        const choose = (target) => {
            const g = target.closest('[data-seat]');
            if (!g || !data) return;
            const seat = data.seats.find((s) => String(s.id) === g.dataset.seat);
            if (!seat) return;
            if (opts.mode === 'view') {
                if (seat.order_id && opts.orderUrl) window.location.href = opts.orderUrl.replace('__ID__', seat.order_id);
                return;
            }
            if (!pickable(seat)) return;
            selected = seat.id;
            render();
            opts.onPick && opts.onPick(seat, data);
        };

        svg.addEventListener('click', (e) => choose(e.target));
        svg.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(e.target); } });

        load();

        return {
            reload: load,
            setType(typeId) { opts.typeId = typeId; if (data) render(); },
            destroy() { clearTimeout(timer); },
        };
    }

    /* ------------------------------------------------------------------ editor */

    function editor(root, layout) {
        const svg = root.querySelector('svg[data-map]');
        const form = root.querySelector('form[data-layout-form]');
        const field = form.querySelector('input[name="layout"]');
        const panel = root.querySelector('[data-props]');
        const info = root.querySelector('[data-editor-status]');
        const q = (sel) => root.querySelector(sel);

        let uid = 0;
        const seats = (layout.seats || []).map((s) => ({ ...s, uid: ++uid }));
        const areas = (layout.areas || []).map((a) => ({ ...a, uid: ++uid }));
        const W = layout.width;
        const H = layout.height;
        let tool = 'select';
        let selection = { kind: null, uids: new Set() };
        let drag = null;
        let dirty = false;

        zoomControls(root, svg);

        const clamp = (v, min, max) => Math.max(min, Math.min(max, Math.round(v)));
        const snap = (v) => Math.round(v / 5) * 5;
        const point = (e) => {
            const pt = svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            return pt.matrixTransform(svg.getScreenCTM().inverse());
        };
        const categories = () => [...new Set(seats.map((s) => s.category).filter(Boolean))].sort();
        const selectedSeats = () => (selection.kind === 'seat' ? seats.filter((s) => selection.uids.has(s.uid)) : []);
        const selectedArea = () => (selection.kind === 'area' ? areas.find((a) => selection.uids.has(a.uid)) : null);
        const touch = () => { dirty = true; render(); };

        const defaults = () => ({
            prefix: (q('[data-new="prefix"]').value || '').trim(),
            category: (q('[data-new="category"]').value || '').trim() || null,
            shape: q('[data-new="shape"]').value || 'circle',
            w: clamp(Number(q('[data-new="w"]').value) || 40, 10, 1000),
            h: clamp(Number(q('[data-new="h"]').value) || 40, 10, 1000),
            places: Number(q('[data-new="places"]').value) || null,
        });

        // Próximo número do prefixo: M1, M2… continua do maior que já existe.
        const nextNumber = (prefix) => {
            const re = new RegExp(`^${prefix.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(\\d+)$`, 'i');
            return seats.reduce((max, s) => { const m = String(s.label).match(re); return m ? Math.max(max, Number(m[1])) : max; }, 0) + 1;
        };

        const addSeat = (x, y, d, label) => {
            const seat = { uid: ++uid, id: null, label, category: d.category, shape: d.shape, x: clamp(x, 0, W), y: clamp(y, 0, H), w: d.w, h: d.h, places: d.places, active: true, locked: false };
            seats.push(seat);
            return seat;
        };

        const setTool = (name) => {
            tool = name;
            root.querySelectorAll('[data-tool]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.tool === name)));
            svg.style.cursor = name === 'select' ? 'default' : 'crosshair';
            q('[data-tool-help]').textContent = {
                select: 'Clique para selecionar (Shift soma), arraste para mover, arraste no vazio para selecionar vários. Setas movem; Delete apaga.',
                seat: 'Clique no mapa para pôr um lugar com o próximo rótulo. Continua na ferramenta até trocar.',
                area: 'Arraste no mapa para desenhar a área (palco, piscina, banheiros). Depois dê o nome ao lado.',
                grid: 'Clique no mapa onde fica o primeiro lugar da grade (canto superior esquerdo).',
            }[name];
        };

        function render() {
            const cats = categories();
            draw(svg, { width: W, height: H, background_url: layout.background_url, areas, seats }, {
                areaCursor: tool === 'select' ? 'move' : 'crosshair',
                areaSelected: (index) => selection.kind === 'area' && selection.uids.has(areas[index].uid),
                seatStyle: (seat) => {
                    const sel = selection.kind === 'seat' && selection.uids.has(seat.uid);
                    const t = categoryTone(seat.category, cats);
                    return {
                        fill: sel ? tone('grena') : (t ? tone(t) : tone('surface')),
                        stroke: sel ? tone('grena-hover') : (t ? tone(`${t}-ink`) : tone('ink-2')),
                        text: sel ? '#fff' : (t ? tone(`${t}-ink`) : tone('ink')),
                        dash: seat.active ? '' : '4 3',
                        strokeWidth: sel ? 3 : 2,
                        cursor: tool === 'select' ? 'move' : 'crosshair',
                        badge: seat.locked ? '🔒' : null,
                    };
                },
                seatTitle: (seat) => [seatDescription(seat), seat.active ? null : 'desativado', seat.locked ? 'já teve ingresso: rótulo e categoria travados' : null].filter(Boolean).join(' · '),
            });
            if (drag && drag.band) {
                const b = drag.band;
                svg.appendChild(node('rect', { x: Math.min(b.x0, b.x1), y: Math.min(b.y0, b.y1), width: Math.abs(b.x1 - b.x0), height: Math.abs(b.y1 - b.y0) },
                    { fill: tone('grena', 0.08), stroke: tone('grena'), strokeDasharray: '4 3' }));
            }
            if (drag && drag.newArea) {
                const a = drag.newArea;
                svg.appendChild(node('rect', { x: Math.min(a.x0, a.x1), y: Math.min(a.y0, a.y1), width: Math.abs(a.x1 - a.x0), height: Math.abs(a.y1 - a.y0), rx: 6 },
                    { fill: tone('ink-3', 0.12), stroke: tone('ink-3'), strokeDasharray: '6 4' }));
            }
            renderPanel();
            const active = seats.filter((s) => s.active).length;
            info.textContent = `${seats.length} lugar(es) (${active} ativo(s)) · ${areas.length} área(s)` + (dirty ? ' · alterações não salvas' : '');
            info.classList.toggle('text-warn', dirty);
        }

        /* Painel de propriedades da seleção. */
        function renderPanel() {
            const list = selectedSeats();
            const area = selectedArea();
            const show = (name) => panel.querySelectorAll('[data-for]').forEach((el) => {
                el.style.display = el.dataset.for === name ? '' : 'none';
            });

            if (area) {
                show('area');
                setField('area-label', area.label || '');
                setField('area-shape', area.shape || 'rect');
                setField('area-color', area.color || 'service');
                setField('area-w', area.w);
                setField('area-h', area.h);
                return;
            }
            if (list.length === 0) {
                show('none');
                return;
            }

            show('seat');
            const one = list.length === 1 ? list[0] : null;
            const same = (key) => (list.every((s) => s[key] === list[0][key]) ? list[0][key] : '');
            const locked = list.some((s) => s.locked);
            panel.querySelector('[data-seat-count]').textContent = one ? `Lugar ${one.label}` : `${list.length} lugares selecionados`;
            setField('seat-label', one ? one.label : '', !one || one.locked);
            setField('seat-category', same('category') ?? '', locked);
            setField('seat-shape', same('shape') || '');
            setField('seat-w', same('w'));
            setField('seat-h', same('h'));
            setField('seat-places', same('places') ?? '');
            const act = panel.querySelector('[data-prop="seat-active"]');
            act.checked = list.every((s) => s.active);
            act.indeterminate = !act.checked && list.some((s) => s.active);
            panel.querySelector('[data-locked-note]').style.display = locked ? '' : 'none';
            panel.querySelectorAll('[data-align]').forEach((b) => { b.disabled = list.length < 2; });
        }

        function setField(name, value, disabled) {
            const el = panel.querySelector(`[data-prop="${name}"]`);
            if (document.activeElement !== el) el.value = value ?? '';
            el.disabled = !!disabled;
        }

        panel.addEventListener('input', (e) => {
            const prop = e.target.dataset.prop;
            if (!prop) return;
            const value = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
            const area = selectedArea();
            if (area && prop.startsWith('area-')) {
                const key = prop.slice(5);
                area[key] = ['w', 'h'].includes(key) ? clamp(Number(value) || 10, 10, key === 'w' ? W : H) : value;
                return touch();
            }
            const list = selectedSeats();
            if (!list.length || !prop.startsWith('seat-')) return;
            const key = prop.slice(5);
            list.forEach((s) => {
                if (s.locked && (key === 'label' || key === 'category')) return;
                if (key === 'w' || key === 'h') s[key] = clamp(Number(value) || 10, 10, 1000);
                else if (key === 'places') s.places = value === '' ? null : clamp(Number(value), 1, 255);
                else if (key === 'category') s.category = value.trim() || null;
                else if (key === 'label') s.label = value.trim();
                else s[key] = value;
            });
            touch();
        });

        panel.addEventListener('click', (e) => {
            const btn = e.target.closest('button');
            if (!btn) return;
            if (btn.dataset.remove !== undefined) removeSelection();
            if (btn.dataset.align) {
                const list = selectedSeats();
                const key = btn.dataset.align === 'row' ? 'y' : 'x';
                const avg = list.reduce((sum, s) => sum + s[key], 0) / list.length;
                list.forEach((s) => { s[key] = Math.round(avg); });
                touch();
            }
            if (btn.dataset.distribute) {
                const list = selectedSeats();
                const key = btn.dataset.distribute === 'row' ? 'x' : 'y';
                const sorted = [...list].sort((a, b) => a[key] - b[key]);
                const first = sorted[0][key];
                const step = (sorted[sorted.length - 1][key] - first) / Math.max(1, sorted.length - 1);
                sorted.forEach((s, i) => { s[key] = Math.round(first + step * i); });
                touch();
            }
        });

        function removeSelection() {
            if (selection.kind === 'area') {
                const keep = areas.filter((a) => !selection.uids.has(a.uid));
                areas.splice(0, areas.length, ...keep);
            } else {
                const blocked = selectedSeats().filter((s) => s.locked);
                if (blocked.length) {
                    window.alert(`Os lugares ${blocked.map((s) => s.label).join(', ')} já tiveram ingresso: desative em vez de apagar.`);
                }
                const keep = seats.filter((s) => !selection.uids.has(s.uid) || s.locked);
                seats.splice(0, seats.length, ...keep);
            }
            selection = { kind: null, uids: new Set() };
            touch();
        }

        /* Ponteiro no mapa. */
        svg.addEventListener('pointerdown', (e) => {
            if (e.button !== 0) return;
            const p = point(e);
            const seatEl = e.target.closest('[data-seat]');
            const areaEl = e.target.closest('[data-area]');
            svg.setPointerCapture(e.pointerId);

            if (tool === 'seat') {
                const d = defaults();
                const seat = addSeat(snap(p.x), snap(p.y), d, `${d.prefix}${nextNumber(d.prefix)}`);
                selection = { kind: 'seat', uids: new Set([seat.uid]) };
                return touch();
            }

            if (tool === 'grid') {
                const d = defaults();
                const rows = clamp(Number(q('[data-grid="rows"]').value) || 1, 1, 50);
                const cols = clamp(Number(q('[data-grid="cols"]').value) || 1, 1, 50);
                const gx = clamp(Number(q('[data-grid="gap-x"]').value) || 20, 0, 500) + d.w;
                const gy = clamp(Number(q('[data-grid="gap-y"]').value) || 20, 0, 500) + d.h;
                let n = nextNumber(d.prefix);
                const added = [];
                for (let r = 0; r < rows; r++) {
                    for (let c = 0; c < cols; c++) {
                        const x = snap(p.x) + d.w / 2 + c * gx;
                        const y = snap(p.y) + d.h / 2 + r * gy;
                        if (x <= W && y <= H) added.push(addSeat(x, y, d, `${d.prefix}${n++}`).uid);
                    }
                }
                selection = { kind: 'seat', uids: new Set(added) };
                setTool('select');
                return touch();
            }

            if (tool === 'area') {
                drag = { newArea: { x0: snap(p.x), y0: snap(p.y), x1: snap(p.x), y1: snap(p.y) } };
                return render();
            }

            // Selecionar / mover.
            if (seatEl || areaEl) {
                const kind = seatEl ? 'seat' : 'area';
                const id = seatEl ? Number(seatEl.dataset.seat) : areas[Number(areaEl.dataset.area)].uid;
                if (selection.kind !== kind || !selection.uids.has(id)) {
                    selection = e.shiftKey && selection.kind === kind && kind === 'seat'
                        ? { kind, uids: new Set([...selection.uids, id]) }
                        : { kind, uids: new Set([id]) };
                } else if (e.shiftKey && kind === 'seat') {
                    selection.uids.delete(id);
                }
                const items = kind === 'seat' ? selectedSeats() : areas.filter((a) => selection.uids.has(a.uid));
                drag = { move: true, x: p.x, y: p.y, start: items.map((it) => [it, it.x, it.y]), moved: false };
                return render();
            }

            if (!e.shiftKey) selection = { kind: null, uids: new Set() };
            drag = { band: { x0: p.x, y0: p.y, x1: p.x, y1: p.y } };
            render();
        });

        svg.addEventListener('pointermove', (e) => {
            if (!drag) return;
            const p = point(e);
            if (drag.move) {
                const dx = snap(p.x - drag.x);
                const dy = snap(p.y - drag.y);
                if (dx === 0 && dy === 0 && !drag.moved) return;
                drag.moved = true;
                drag.start.forEach(([it, x0, y0]) => {
                    it.x = clamp(x0 + dx, 0, W);
                    it.y = clamp(y0 + dy, 0, H);
                });
                dirty = true;
            } else if (drag.band) {
                drag.band.x1 = p.x;
                drag.band.y1 = p.y;
            } else if (drag.newArea) {
                drag.newArea.x1 = clamp(snap(p.x), 0, W);
                drag.newArea.y1 = clamp(snap(p.y), 0, H);
            }
            render();
        });

        const finish = () => {
            if (!drag) return;
            if (drag.band) {
                const b = drag.band;
                const [x0, x1] = [Math.min(b.x0, b.x1), Math.max(b.x0, b.x1)];
                const [y0, y1] = [Math.min(b.y0, b.y1), Math.max(b.y0, b.y1)];
                const inside = seats.filter((s) => s.x >= x0 && s.x <= x1 && s.y >= y0 && s.y <= y1).map((s) => s.uid);
                if (inside.length) selection = { kind: 'seat', uids: new Set([...(selection.kind === 'seat' ? selection.uids : []), ...inside]) };
            }
            if (drag.newArea) {
                const a = drag.newArea;
                let [x, y, w, h] = [Math.min(a.x0, a.x1), Math.min(a.y0, a.y1), Math.abs(a.x1 - a.x0), Math.abs(a.y1 - a.y0)];
                if (w < 10 || h < 10) { w = 160; h = 80; }
                const area = { uid: ++uid, label: '', shape: 'rect', x: clamp(x, 0, W), y: clamp(y, 0, H), w: clamp(w, 10, W), h: clamp(h, 10, H), color: 'service' };
                areas.push(area);
                selection = { kind: 'area', uids: new Set([area.uid]) };
                setTool('select');
                dirty = true;
                drag = null;
                render();
                panel.querySelector('[data-prop="area-label"]').focus();
                return;
            }
            drag = null;
            render();
        };
        svg.addEventListener('pointerup', finish);
        svg.addEventListener('pointercancel', finish);

        document.addEventListener('keydown', (e) => {
            if (['INPUT', 'SELECT', 'TEXTAREA'].includes(document.activeElement?.tagName)) return;
            if (!selection.kind) return;
            if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); return removeSelection(); }
            const step = e.shiftKey ? 10 : 1;
            const delta = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
            if (!delta) return;
            e.preventDefault();
            const items = selection.kind === 'seat' ? selectedSeats() : [selectedArea()];
            items.forEach((it) => { it.x = clamp(it.x + delta[0], 0, W); it.y = clamp(it.y + delta[1], 0, H); });
            touch();
        });

        root.querySelectorAll('[data-tool]').forEach((b) => b.addEventListener('click', () => setTool(b.dataset.tool)));

        form.addEventListener('submit', () => {
            field.value = JSON.stringify({
                seats: seats.map(({ id, label, category, shape, x, y, w, h, places, active }) => ({ id, label, category, shape, x, y, w, h, places, active })),
                areas: areas.map(({ label, shape, x, y, w, h, color }) => ({ label, shape, x, y, w, h, color })),
            });
            dirty = false;
        });
        window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

        setTool('select');
        render();
    }

    window.TicketSeatMap = { viewer, editor, STATUS_LABELS };
})();
