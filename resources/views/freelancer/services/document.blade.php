<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $service->isAmendment() ? 'Aditivo' : 'Contrato' }} #{{ $service->id }} — {{ $service->freelancer->name }}</title>
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

@include('freelancer.services.partials.contract-document-styles')

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
        <span class="title">{{ $service->isAmendment() ? 'Aditivo' : 'Contrato' }} #{{ $service->id }} · {{ $service->freelancer->name }}</span>
        <span class="sp"></span>
        <button class="print" onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="sheet-wrap">
        @include('freelancer.services.partials.contract-document', ['service' => $service])
    </div>
</body>
</html>
