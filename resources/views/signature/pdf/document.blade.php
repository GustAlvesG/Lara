@php
    /**
     * O documento de assinatura, em PDF.
     *
     * Sem fontes externas e sem cores de fundo pesadas — dompdf renderiza
     * melhor assim (mesma decisão de freelancer/batches/pdf).
     *
     * `$body` já vem montado pelo SignatureDocumentRenderer, com as variáveis
     * substituídas e a área de assinatura no lugar do marcador. Por isso sai
     * sem escapar: é HTML saneado na gravação do modelo, não entrada crua.
     */
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: 96px 56px 78px 56px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f1819; line-height: 1.65; }

        header { position: fixed; top: -68px; left: 0; right: 0; height: 56px;
                 border-bottom: 2px solid #A00001; }
        header .club { font-size: 15px; font-weight: bold; color: #A00001; }
        header .doc { font-size: 10.5px; margin-top: 2px; color: #6d6062; }

        footer { position: fixed; bottom: -56px; left: 0; right: 0; height: 40px;
                 border-top: 1px solid #d8cbc9; padding-top: 7px;
                 font-size: 8px; color: #777; line-height: 1.5; }

        h1 { font-size: 15px; margin: 0 0 14px; text-align: center; text-transform: uppercase; letter-spacing: .5px; }
        h2 { font-size: 12.5px; margin: 16px 0 7px; }
        h3, h4 { font-size: 11.5px; margin: 13px 0 6px; }
        p { margin: 0 0 9px; text-align: justify; }
        ol, ul { margin: 0 0 9px 16px; padding: 0; }
        li { margin-bottom: 5px; text-align: justify; }
        table { width: 100%; border-collapse: collapse; margin: 0 0 11px; }
        td, th { border: 1px solid #d8cbc9; padding: 5px 7px; font-size: 10px; vertical-align: top; }
        th { background: #f2ecea; text-align: left; }
        hr { border: none; border-top: 1px solid #d8cbc9; margin: 14px 0; }

        /* Área de assinatura — ver signature-area.blade.php. */
        .sig-area { margin-top: 30px; page-break-inside: avoid; }
        .sig-block { margin-top: 26px; page-break-inside: avoid; }
        .sig-img { height: 68px; margin-bottom: -6px; }
        .sig-line { border-top: 1px solid #1f1819; width: 300px; padding-top: 5px; }
        .sig-name { font-size: 10.5px; font-weight: bold; }
        .sig-meta { font-size: 9px; color: #6d6062; }
        .sig-pending { font-size: 9px; color: #9a8e90; font-style: italic; }
    </style>
</head>
<body>
    <header>
        <div class="club">Clube dos Funcionários da CSN</div>
        <div class="doc">{{ $document->title }}</div>
    </header>

    <footer>
        @if($document->validation_code)
            Documento eletrônico. Confira a autenticidade em {{ config('app.url') }}/validar/{{ $document->validation_code }}
            — código {{ $document->validation_code }}.
        @else
            Documento eletrônico — prévia não congelada, sem valor de via assinada.
        @endif
    </footer>

    <h1>{{ $document->title }}</h1>

    {!! $body !!}
</body>
</html>
