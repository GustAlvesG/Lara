@php
    /**
     * Corpo do e-mail da via assinada.
     *
     * Curto e sem dados pessoais: o documento anexo já os tem, e e-mail é
     * reencaminhado com facilidade que o papel não tem.
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
      Segue em anexo a sua via assinada do documento <b>{{ $document->title }}</b>,
      firmado em {{ $signer->signed_at?->format('d/m/Y \à\s H:i') }}.
    </p>

    <p style="font-size:15px;line-height:1.6;margin:0 0 14px;">
      O arquivo traz uma página de manifesto com o registro da assinatura e um código de validação.
      Para conferir a autenticidade a qualquer momento, acesse:
    </p>

    <p style="margin:0 0 18px;">
      <a href="{{ $validationUrl }}" style="display:inline-block;background:#A00001;color:#fff;text-decoration:none;padding:12px 20px;border-radius:10px;font-weight:bold;font-size:14px;">
        Validar documento
      </a>
    </p>

    <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 6px;">
      Código de validação: <b style="letter-spacing:1px;">{{ $document->validation_code }}</b>
    </p>

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Esta mensagem foi enviada porque você pediu a via no momento da assinatura, no atendimento do clube.
      Não é necessário responder.
    </p>
  </div>
</body>
</html>
