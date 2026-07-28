<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contrato #{{ $service->id }} — {{ $service->freelancer->name }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <style>
        :root{ --paper:#fffefb; --ink:#241f1a; --line:#c9bfb4; --muted:#6b6156; --red:#c8102e; --red2:#a30711;
            --serif: ui-serif, Georgia, "Times New Roman", serif; --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        *{ box-sizing:border-box; }
        html,body{ margin:0; padding:0; }
        body{ background:#e9e6e2; font-family:var(--sans); color:var(--ink); }

        .toolbar{ position:sticky; top:0; z-index:10; display:flex; align-items:center; gap:12px;
            background:#1f1819; color:#fff; padding:12px 18px; }
        .toolbar .sp{ flex:1; }
        .toolbar a, .toolbar button{ font-family:inherit; font-size:14px; font-weight:700; cursor:pointer;
            border-radius:10px; padding:10px 16px; border:1px solid transparent; text-decoration:none; }
        .toolbar .back{ background:transparent; color:#d9cfd0; border-color:rgba(255,255,255,.25); }
        .toolbar .print{ background:#A00001; color:#fff; }
        .toolbar .title{ font-size:14px; font-weight:600; color:#cfc7c8; }

        .sheet-wrap{ padding:24px 16px 60px; display:flex; justify-content:center; }

        /* ---------- Documento ---------- */
        .doc{ width:100%; max-width:820px; background:var(--paper); color:var(--ink); font-family:var(--serif);
            box-shadow:0 20px 50px -24px rgba(0,0,0,.5); border-radius:6px; overflow:hidden; }
        .doc-header-img img{ display:block; width:100%; height:auto; }
        .doc-footer-img{ margin-top:26px; }
        .doc-footer-img img{ display:block; width:100%; height:auto; }
        .doc-title{ text-align:center; font-size:18px; font-weight:800; margin:26px 34px 10px; font-family:var(--sans); }
        .doc-body{ padding:8px 40px 6px; }
        .doc-body p{ margin:0 0 14px; font-size:15px; text-align:justify; line-height:1.65; }
        .doc-place{ margin-top:22px !important; }
        .doc-signatures{ display:flex; flex-direction:column; align-items:center; gap:34px; margin:44px 0 14px; }
        .doc-sign-block{ width:min(400px,90%); text-align:center; }
        .doc-sign-empty{ height:74px; }
        .doc-sign-img{ height:74px; display:flex; align-items:flex-end; justify-content:center; }
        .doc-sign-img img{ max-height:74px; max-width:100%; object-fit:contain; }
        .doc-sign-mark{ height:74px; display:flex; align-items:flex-end; justify-content:center; font-family:var(--sans); font-size:12px; color:#157a58; font-weight:700; padding-bottom:4px; }
        .doc-sign-line{ border-top:1.5px solid var(--ink); }
        .doc-sign-name{ font-size:15px; font-weight:800; margin-top:8px; font-family:var(--sans); }
        .doc-sign-role{ font-size:12.5px; color:var(--muted); font-family:var(--sans); margin-top:2px; }
        .doc-sign-note{ font-size:11px; color:var(--muted); font-family:var(--sans); margin-top:4px; }
        /* O cabeçalho (thead) e o rodapé (tfoot) da tabela são repetidos pelo
           navegador em todas as páginas na impressão — sem precisar reservar
           espaço manualmente. */
        .doc-table{ width:100%; border-collapse:collapse; }
        .doc-table > thead > tr > td,
        .doc-table > tfoot > tr > td,
        .doc-table > tbody > tr > td{ padding:0; }

        @media print{
            body{ background:#fff; }
            .toolbar{ display:none; }
            .sheet-wrap{ padding:0; }
            .doc{ max-width:none; box-shadow:none; border-radius:0; overflow:visible; }
            @page{ margin:12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a class="back" href="{{ route('freelancer-services.show', $service) }}">← Voltar</a>
        <span class="title">Contrato #{{ $service->id }} · {{ $service->freelancer->name }}</span>
        <span class="sp"></span>
        <button class="print" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="sheet-wrap">
        @include('freelancer.services.partials.contract-document', ['service' => $service])
    </div>
</body>
</html>
