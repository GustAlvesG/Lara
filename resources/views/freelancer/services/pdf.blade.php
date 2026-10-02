{{--
    O contrato do freelancer em PDF, para o servidor de arquivos.

    É o MESMO documento da tela de impressão e do tablet
    (`partials/contract-document`, layout `pdf`) — as cláusulas vêm da redação
    que o contrato firmou. O que muda é só a folha de estilo: o DomPDF não
    entende variável de CSS nem flexbox, então aqui as cores são literais e os
    blocos são centralizados por margem.

    As medidas estão em px a 96 dpi (A4 = 794 × 1123). O cabeçalho e o rodapé
    são as imagens da impressão, na largura do texto; a margem da página abre o
    espaço deles, e eles sobem/descem para dentro dela.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $service->documentTitle() }} #{{ $service->id }}</title>
    <style>
        /* Sem `margin:0` em html/body: no DomPDF a margem da página É a margem
           do body, e zerá-la aqui apaga a do @page — o texto sobe para cima do
           cabeçalho. */
        @page { margin: 205px 45px 140px 45px; }
        *{ box-sizing:border-box; }
        body{ font-family:"Times New Roman", Times, serif; color:#241f1a; }

        .pdf-header{ position:fixed; top:-175px; left:0; right:0; height:162px; }
        .pdf-footer{ position:fixed; bottom:-118px; left:0; right:0; height:98px; }
        .doc-header-img img, .doc-footer-img img{ display:block; width:704px; }

        .doc-title{ text-align:center; font-size:18px; font-weight:bold; margin:0 34px 14px; font-family:Helvetica, Arial, sans-serif; }
        .doc-body{ padding:0 22px; }
        .doc-body p{ margin:0 0 14px; font-size:15px; text-align:justify; line-height:1.55; }
        .doc-place{ margin-top:22px !important; }

        .doc-signatures{ margin:40px 0 0; }
        .doc-sign-block{ width:400px; margin:0 auto 30px; text-align:center; page-break-inside:avoid; }
        .doc-sign-empty{ height:74px; }
        .doc-sign-img{ height:74px; text-align:center; }
        .doc-sign-img img{ max-height:74px; max-width:380px; }
        .doc-sign-mark{ height:74px; padding-top:54px; font-family:"DejaVu Sans", sans-serif; font-size:11px; color:#157a58; font-weight:bold; }
        .doc-sign-line{ border-top:1.5px solid #241f1a; }
        .doc-sign-name{ font-size:15px; font-weight:bold; margin-top:8px; font-family:Helvetica, Arial, sans-serif; }
        .doc-sign-role{ font-size:12.5px; color:#6b6156; font-family:Helvetica, Arial, sans-serif; margin-top:2px; }
        .doc-sign-note{ font-size:11px; color:#6b6156; font-family:Helvetica, Arial, sans-serif; margin-top:4px; }

        /* Anexo I — relatório de vendas da comissão. */
        .doc-annex{ margin-top:30px; border-top:1.5px solid #c9bfb4; padding-top:16px; }
        .doc-annex-title{ font-family:Helvetica, Arial, sans-serif; font-size:14px; font-weight:bold; margin-bottom:4px; }
        .doc-annex-meta{ font-size:12px !important; margin-bottom:10px !important; }
        table.annex{ width:100%; border-collapse:collapse; font-family:Helvetica, Arial, sans-serif; font-size:11px; }
        table.annex th{ text-align:left; border-bottom:1px solid #c9bfb4; padding:5px 4px; font-size:10px;
            text-transform:uppercase; color:#6b6156; }
        table.annex td{ padding:4px; border-bottom:1px solid #eeeae6; vertical-align:top; }
        table.annex .num{ text-align:right; white-space:nowrap; }
        table.annex .annex-sec{ font-weight:bold; padding-top:9px; background:#f3f1ee; font-size:10px; }
        table.annex tr{ page-break-inside:avoid; }
    </style>
</head>
<body>
    @include('freelancer.services.partials.contract-document', ['service' => $service, 'layout' => 'pdf'])
</body>
</html>
