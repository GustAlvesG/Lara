<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Assinatura de Documentos — CFCSN</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
</head>
<body>
  {{--
    Tela do tablet. A interface completa (leitor de QR, PDF.js, identidade,
    aceite, assinatura e foto) entra na Fase 4; este arquivo existe desde a
    Fase 3 porque o middleware da sessão redireciona para cá quando um
    navegador comum cai numa rota do quiosque.
  --}}
  <p>Quiosque de assinatura.</p>
</body>
</html>
