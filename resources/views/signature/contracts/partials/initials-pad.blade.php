{{--
    Quadro do visto (rubrica) do gerente ao aprovar o contrato (04/11/2026).
    Usado na tela do contrato (computador, mouse) e no tablet da Gerência
    (dedo). Estilos próprios, sem Tailwind: o tablet tem CSS à parte.

    Preenche os campos `initials` (PNG em data URL) e `initials_strokes`
    (traços em JSON, para o servidor contar os pontos — mesma régua do visto
    do tablet de assinatura). Validação e gravação: ContractApprovalInitials.

    $id: id do quadro (um por página). JS: window.laraInitialsPad(elemento)
    devolve { points(), data(), strokes(), clear() }.
--}}
@php $id = $id ?? 'vistoGerencia'; @endphp
<div class="initials-pad" id="{{ $id }}" data-initials-pad style="max-width:420px;">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
        <span style="font-size:13px;font-weight:700;">Seu visto (rubrica) — vai em todas as páginas do contrato</span>
        <button type="button" data-initials-clear style="font-size:13px;font-weight:700;background:none;border:none;color:#8a1538;cursor:pointer;padding:6px 0;">Limpar</button>
    </div>
    <canvas data-initials-canvas width="840" height="260"
            style="display:block;width:100%;height:130px;border:2px dashed #d0c7c9;border-radius:12px;background:#fff;touch-action:none;cursor:crosshair;"></canvas>
    <input type="hidden" name="initials" data-initials-image>
    <input type="hidden" name="initials_strokes" data-initials-strokes>
</div>

@once
<script>
    window.laraInitialsPad = window.laraInitialsPad || function (raiz) {
        if (raiz.__pad) return raiz.__pad;

        var canvas = raiz.querySelector('[data-initials-canvas]');
        var ctx = canvas.getContext('2d');
        var tracos = [];
        var atual = null;

        ctx.lineWidth = 5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#1e1215';

        function ponto(e) {
            var r = canvas.getBoundingClientRect();
            return { x: Math.round((e.clientX - r.left) * canvas.width / r.width), y: Math.round((e.clientY - r.top) * canvas.height / r.height) };
        }

        function grava() {
            var total = tracos.reduce(function (n, t) { return n + t.length; }, 0);
            raiz.querySelector('[data-initials-image]').value = total ? canvas.toDataURL('image/png') : '';
            raiz.querySelector('[data-initials-strokes]').value = total ? JSON.stringify(tracos) : '';
        }

        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            canvas.setPointerCapture(e.pointerId);
            var p = ponto(e);
            atual = [p];
            tracos.push(atual);
            ctx.beginPath();
            ctx.moveTo(p.x, p.y);
            ctx.lineTo(p.x + 0.1, p.y + 0.1);
            ctx.stroke();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!atual) return;
            var p = ponto(e);
            atual.push(p);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
        });
        ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) {
            canvas.addEventListener(ev, function () { if (atual) { atual = null; grava(); } });
        });

        var api = {
            points: function () { return tracos.reduce(function (n, t) { return n + t.length; }, 0); },
            data: function () { return raiz.querySelector('[data-initials-image]').value; },
            strokes: function () { return tracos; },
            clear: function () { tracos = []; atual = null; ctx.clearRect(0, 0, canvas.width, canvas.height); grava(); }
        };

        raiz.querySelector('[data-initials-clear]').addEventListener('click', api.clear);
        raiz.__pad = api;

        return api;
    };

    document.querySelectorAll('[data-initials-pad]').forEach(function (el) { window.laraInitialsPad(el); });
</script>
@endonce
