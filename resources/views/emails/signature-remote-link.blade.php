@php
    /**
     * Link de assinatura remota. Mesmo visual do convite do gov.br e da via.
     *
     * O link abre o site do clube, não o Lara. Sem CPF nem conteúdo do
     * documento no corpo: a pessoa lê na tela, depois de confirmar o CPF.
     */
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px;background:#efe9e8;font-family:Arial,Helvetica,sans-serif;color:#1f1819;">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:26px;">

    <div style="border-bottom:2px solid #A00001;padding-bottom:10px;margin-bottom:18px;">
      <b style="color:#A00001;font-size:16px;">Clube dos Funcionários da CSN</b>
    </div>

    <p style="font-size:15px;line-height:1.6;margin:0 0 14px;">Olá, {{ $signer->name }}.</p>

    <p style="font-size:15px;line-height:1.6;margin:0 0 14px;">
      O clube enviou o documento <b>{{ $document->title }}</b> para você assinar, sem precisar vir até aqui.
    </p>

    <p style="text-align:center;margin:22px 0;">
      <a href="{{ $url }}"
         style="display:inline-block;background:#A00001;color:#fff;text-decoration:none;font-weight:bold;font-size:15px;padding:13px 26px;border-radius:999px;">
        Ler e assinar o documento
      </a>
    </p>

    <ol style="font-size:15px;line-height:1.6;margin:0 0 16px;padding-left:20px;">
      <li style="margin-bottom:6px;">Use um <b>celular ou computador com câmera</b>: no fim, o sistema tira uma foto sua como registro da assinatura.</li>
      <li style="margin-bottom:6px;">Tenha o seu <b>CPF</b> à mão — ele é pedido antes de abrir o documento.</li>
      @php $anexosPedidos = $document->attachmentRequirements(); @endphp
      @if($anexosPedidos !== [])
        <li style="margin-bottom:6px;">
          Separe, em foto ou PDF, para enviar pela própria tela:
          {{ collect($anexosPedidos)->map(fn($a) => $a['label'] . ($a['required'] ? '' : ' (opcional)'))->join(', ') }}.
        </li>
      @endif
      <li>Leia o documento até o fim, aceite e assine na tela com o dedo ou o mouse.</li>
    </ol>

    <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 6px;">
      O link vale até <b>{{ $link->expires_at->format('d/m/Y \à\s H:i') }}</b> e é só seu: não o encaminhe.
      Se precisar de um novo, peça ao clube.
    </p>

    <p style="font-size:12px;line-height:1.6;color:#6d6062;margin:12px 0 0;word-break:break-all;">
      Se o botão não abrir, copie este endereço no navegador:<br>{{ $url }}
    </p>

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Esta mensagem foi enviada pelo atendimento do clube{{ $link->sent_by_name ? ' (' . $link->sent_by_name . ')' : '' }}.
      Se você não esperava este documento, ignore esta mensagem.
    </p>
  </div>
</body>
</html>
