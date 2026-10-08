@php
    /**
     * O código da conferência de identidade, digitado no tablet do balcão.
     * Mesmo visual do e-mail da via. Sem dados pessoais no corpo.
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
      Para confirmar a sua identidade e assinar o documento <b>{{ $document->title }}</b>, digite no tablet o código:
    </p>

    <p style="margin:0 0 18px;text-align:center;">
      <span style="display:inline-block;font-size:30px;font-weight:bold;letter-spacing:8px;background:#f2ecea;border-radius:10px;padding:12px 22px;">{{ $code }}</span>
    </p>

    <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 6px;">
      O código vale por {{ $minutes }} minutos e serve só para esta assinatura. Não o informe a ninguém além do tablet.
    </p>

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Você recebeu esta mensagem porque está assinando um documento no atendimento do clube. Se não está, ignore
      esta mensagem.
    </p>
  </div>
</body>
</html>
