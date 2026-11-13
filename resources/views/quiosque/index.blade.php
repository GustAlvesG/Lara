@php
    /*
     | Dois modos, a mesma tela:
     |  - balcao  (/assinatura/kiosk): lê o QR que o atendente gerou;
     |  - menores (/assinatura/kiosk/menores): autoatendimento do Termo de
     |    Menores — o sócio informa o título, escolhe o responsável, confirma o
     |    CPF e escolhe o menor; o próprio tablet gera o documento. Leitura,
     |    aceite, traço e foto são os mesmos. Ver MinorTermKioskController.
     */
    $modo = $modo ?? 'balcao';
    $menores = $modo === 'menores';

    $configMenores = $menores ? [
        'pareado' => (bool) ($pareado ?? false),
        'prefixoPareamento' => \App\Models\SignatureKioskDevice::PAIRING_PREFIX,
        'rotas' => [
            'parear' => route('quiosque.menores.pair'),
            'estado' => route('quiosque.menores.state'),
            'titulo' => route('quiosque.menores.title'),
            'responsavel' => route('quiosque.menores.responsible'),
            'lista' => route('quiosque.menores.minors'),
            'documento' => route('quiosque.menores.document'),
            'encerrar' => route('quiosque.menores.end'),
        ],
        // Inatividade, em segundos, que devolve o tablet ao início nas telas
        // de antes do documento: o próximo sócio não vê os nomes do anterior.
        'ociosoSegundos' => 120,
    ] : null;

    // Assinatura da Diretoria: só no balcão. O diretor entra com matrícula e
    // PIN e vê os documentos dele. Ver DirectorKioskController.
    $configDiretoria = $menores ? null : [
        'rotas' => [
            'entrar' => route('quiosque.diretoria.login'),
            'pendentes' => route('quiosque.diretoria.pending'),
            'abrir' => route('quiosque.diretoria.open', ['signatureSigner' => '__SIGNER__']),
            'sair' => route('quiosque.diretoria.logout'),
            // Assinatura em lote (vários documentos de uma vez).
            'lote' => route('quiosque.diretoria.batch.open'),
            'loteVisualizado' => route('quiosque.diretoria.batch.viewed', ['signatureSigner' => '__SIGNER__']),
            'loteAssinar' => route('quiosque.diretoria.batch.sign'),
            'loteCancelar' => route('quiosque.diretoria.batch.cancel'),
        ],
        // Sem toque na lista por este tempo, a entrada do diretor acaba
        // (SIGNATURE_DIRECTOR_IDLE_SECONDS).
        'ociosoSegundos' => app(\App\Services\Signature\Directors\DirectorKioskService::class)->idleSeconds(),
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
{{--
    Tablet de assinatura (/assinatura/kiosk e /assinatura/kiosk/menores).
    Desde 11/11/2026 esta página é só o esqueleto:
      - telas em quiosque/telas/ (balcao, menores, diretoria, avisos);
      - JavaScript em quiosque/js/ (nucleo + menores ou diretoria + inicio,
        que formam UMA IIFE — ver o comentário de js/nucleo);
      - visual em resources/css/quiosque.css (Vite), na identidade do módulo
        Assinaturas, em pé ou deitado.

    NADA é guardado no aparelho: sem localStorage, sem IndexedDB. O estado vive
    em memória e é zerado ao fim de cada atendimento. Todo horário exibido vem
    do servidor.

    A câmera exige HTTPS (getUserMedia só funciona em origem segura). Ver o
    README do módulo.
--}}
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#152652">
  <title>{{ $menores ? 'Termo de Menores' : 'Assinatura de Documentos' }} — CFCSN</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

  <script>window.QUIOSQUE = {
    csrf: @json(csrf_token()),
    rotas: {
      consumir: @json(route('quiosque.consume')),
      sessao: @json(route('quiosque.session')),
      encerrar: @json(route('quiosque.leave')),
      // Os caminhos por documento são montados na hora, com o id que a leitura
      // do QR devolveu — o tablet nunca escolhe um id.
      respostas: @json(route('quiosque.answers', ['signatureDocument' => '__DOC__'])),
      rascunho: @json(route('quiosque.answers-draft', ['signatureDocument' => '__DOC__'])),
      visualizado: @json(route('quiosque.viewed', ['signatureDocument' => '__DOC__'])),
      identidade: @json(route('quiosque.identity', ['signatureDocument' => '__DOC__'])),
      codigoIdentidade: @json(route('quiosque.identity-code', ['signatureDocument' => '__DOC__'])),
      // "Não recebi o código" e o documento de identidade no lugar dele (13/11/2026).
      naoRecebi: @json(route('quiosque.identity-code-not-received', ['signatureDocument' => '__DOC__'])),
      documentoIdentidade: @json(route('quiosque.identity-document', ['signatureDocument' => '__DOC__'])),
      assinar: @json(route('quiosque.sign', ['signatureDocument' => '__DOC__'])),
      recusar: @json(route('quiosque.refuse', ['signatureDocument' => '__DOC__'])),
    },
    prefixoQr: @json(\App\Models\SignatureRequest::TOKEN_PREFIX),
    minPontos: {{ (int) config('signature.evidence.min_stroke_points', 30) }},
    // O visto (rubrica) é um desenho curto: o mínimo dele é outro.
    minPontosVisto: {{ (int) config('signature.evidence.min_initials_points', 8) }},
    clube: 'Clube dos Funcionários da CSN',
    // Modo sem HTTPS: entrada por código digitado e conclusão sem foto quando
    // não há câmera. Ver o bloco "Modo sem HTTPS" em config/signature.php.
    codigoManual: {{ config('signature.manual_code.enabled') ? 'true' : 'false' }},
    podePularFoto: {{ config('signature.evidence.skip_photo_without_camera') ? 'true' : 'false' }},
    motivoSemCamera: @json(\App\Models\SignatureEvidence::PHOTO_SKIP_NO_CAMERA),
    // O texto da autorização da foto. Vem do servidor porque é o MESMO que a
    // evidência grava — e o corpo desta página não passa pelo Blade.
    textoAutorizacaoFoto: @json(\App\Models\SignatureEvidence::PHOTO_CONSENT_TEXT),
    // Autoatendimento do Termo de Menores; null no balcão.
    menores: @json($configMenores),
    // Assinatura da Diretoria; null no Termo de Menores.
    diretoria: @json($configDiretoria),
    // Validação de contratos pela Gerência (tela própria); null no Termo de Menores.
    gerencia: @json($menores ? null : route('quiosque.gerencia.index')),
  };</script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800|unbounded:500,600,700|jetbrains-mono:500,600&display=swap" rel="stylesheet" />
  @vite('resources/css/quiosque.css')
</head>
<body>
<div class="app">

  <div class="topbar q-band">
    <div class="brand-logo"><img src="{{ asset('images/logo.png') }}" alt=""></div>
    <div class="who">
      <b id="tituloDoc">Assinatura de documentos</b>
      <span id="subtituloDoc">Clube dos Funcionários da CSN</span>
    </div>
    <div class="pill hidden" id="relogio">--:--</div>
  </div>

  <div class="screens">
    @include('quiosque.telas.balcao')

    @if($menores)
      @include('quiosque.telas.menores')
    @else
      @include('quiosque.telas.diretoria')
    @endif

    @include('quiosque.telas.avisos')
  </div>
</div>

<script>
@include('quiosque.js.nucleo')
@if($menores)
@include('quiosque.js.menores')
@else
@include('quiosque.js.diretoria')
@endif
@include('quiosque.js.inicio')
</script>
</body>
</html>
