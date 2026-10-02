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
     *
     * As medidas da página vêm de `$geometry` (SignaturePageGeometry): margens,
     * cabeçalho e rodapé mudam com o papel timbrado e com a faixa do visto, e
     * o visto é desenhado depois, direto na página, contando com elas.
     */
    $cabecalho = $geometry->headerImage();
    $rodape = $geometry->footerImage();
    $sangra = $geometry->fullWidth();
    $lado = \App\Services\Signature\SignaturePageGeometry::SIDE;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $document->title }}</title>
    <style>
        @page { margin: {{ $geometry->marginTop() }}px {{ $lado }}px {{ $geometry->marginBottom() }}px {{ $lado }}px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f1819; line-height: 1.65; }

        header { position: fixed; top: {{ $geometry->headerTop() }}px; left: 0; right: 0; height: 56px;
                 border-bottom: 2px solid #A00001; }
        header .club { font-size: 15px; font-weight: bold; color: #A00001; }
        header .doc { font-size: 10.5px; margin-top: 2px; color: #6d6062; }

        /* Papel timbrado: a imagem da empresa no lugar do cabeçalho de texto.
           Sangrando, sai da margem lateral e vai de borda a borda do papel. */
        .letterhead { position: fixed; left: {{ $sangra ? -$lado : 0 }}px;
                      width: {{ $sangra ? \App\Services\Signature\SignaturePageGeometry::PAGE_WIDTH : \App\Services\Signature\SignaturePageGeometry::PAGE_WIDTH - 2 * $lado }}px;
                      text-align: {{ $sangra ? 'left' : $geometry->align() }}; line-height: 0; }
        .letterhead-top { top: {{ $geometry->headerTop() }}px; }
        .letterhead-bottom { bottom: {{ $geometry->footerImageBottom() }}px; }

        footer { position: fixed; bottom: {{ $geometry->footerTextBottom() }}px; left: 0; right: 0;
                 height: {{ $geometry->footerTextHeight() }}px;
                 border-top: 1px solid #d8cbc9; padding-top: 7px;
                 font-size: 8px; color: #777; line-height: 1.5; }
        footer .company { color: #4a4042; }

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

        /* Partes lado a lado (Contratante | Contratado). É tabela de layout:
           sem a borda e o fundo das tabelas do texto. */
        table.sig-grid { margin: 30px 0 0; page-break-inside: avoid; }
        table.sig-grid td { border: none; padding: 0 14px 0 0; width: 50%; vertical-align: bottom; }
        table.sig-grid .sig-area, table.sig-grid .sig-block { margin-top: 0; }
        table.sig-grid tr + tr .sig-block { margin-top: 26px; }
        table.sig-grid .sig-line { width: 94%; }
    </style>
</head>
<body>
    @if($cabecalho)
        <div class="letterhead letterhead-top">
            <img src="{{ $cabecalho['src'] }}" alt="" style="width:{{ $cabecalho['width'] }}px;height:{{ $cabecalho['height'] }}px;">
        </div>
    @else
        <header>
            <div class="club">Clube dos Funcionários da CSN</div>
            <div class="doc">{{ $document->title }}</div>
        </header>
    @endif

    @if($rodape)
        <div class="letterhead letterhead-bottom">
            <img src="{{ $rodape['src'] }}" alt="" style="width:{{ $rodape['width'] }}px;height:{{ $rodape['height'] }}px;">
        </div>
    @endif

    <footer>
        @if($geometry->footerText())
            <div class="company">{{ $geometry->footerText() }}</div>
        @endif
        @if($document->validation_code)
            Documento eletrônico. Confira a autenticidade em {{ config('app.url') }}/validar/{{ $document->validation_code }}
            — código {{ $document->validation_code }}.
        @else
            Documento eletrônico — prévia não congelada, sem valor de via assinada.
        @endif
    </footer>

    @if($body !== null)
        <h1>{{ $document->title }}</h1>

        {!! $body !!}
    @endif

    @if(!empty($manifest))
        @if($body !== null)
            {{-- A página de manifesto começa em folha nova: é anexo, não continuação. --}}
            <div style="page-break-before: always;"></div>
        @endif
        {!! $manifest !!}
    @endif
</body>
</html>
