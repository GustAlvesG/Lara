@php
    /**
     * Convite para assinar pelo gov.br. Mesmo visual do e-mail da via.
     *
     * Sem link: o Lara não é acessível de fora. A pessoa devolve o arquivo
     * respondendo a este e-mail (vai para o atendente) ou no clube.
     * Sem dados pessoais no corpo: o PDF anexo já os tem.
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
      Segue em anexo o documento <b>{{ $document->title }}</b> para você assinar pelo <b>gov.br</b>,
      com a sua conta gov.br (nível prata ou ouro), sem precisar vir ao clube.
      @if($alreadySigned)
        O arquivo já traz a assinatura de quem assinou antes de você — assine este mesmo arquivo.
      @endif
    </p>

    <ol style="font-size:15px;line-height:1.6;margin:0 0 16px;padding-left:20px;">
      <li style="margin-bottom:6px;">Salve o PDF anexo no seu computador ou celular, <b>sem abrir e salvar de novo</b> em outro programa.</li>
      <li style="margin-bottom:6px;">Acesse <a href="https://assinador.iti.br" style="color:#A00001;">assinador.iti.br</a>, entre com a sua conta gov.br, envie o PDF e assine.</li>
      <li style="margin-bottom:6px;">Baixe o arquivo assinado que o gov.br devolve.</li>
      <li>
        @if($replyGoesToAttendant)
          <b>Responda a este e-mail</b> com esse arquivo anexado — a resposta vai para o atendimento do clube.
        @else
          Devolva esse arquivo ao atendimento do clube.
        @endif
      </li>
    </ol>

    <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 14px;">
      Tem certificado digital ICP-Brasil (e-CPF)? Você também pode assinar o PDF no programa do seu certificado,
      em vez do gov.br, e devolver o arquivo assinado do mesmo jeito.
    </p>

    @if($document->expires_at)
      <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 6px;">
        Prazo para assinar: até <b>{{ $document->expires_at->format('d/m/Y \à\s H:i') }}</b>.
      </p>
    @endif

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Esta mensagem foi enviada pelo atendimento do clube a pedido seu. Se você não esperava este documento,
      ignore esta mensagem.
    </p>
  </div>
</body>
</html>
