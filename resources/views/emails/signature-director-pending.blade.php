@php
    /**
     * Aviso ao diretor: há documento para ele assinar no tablet do clube.
     * Mesmo visual dos demais e-mails do módulo. Sem link — o Lara não é
     * acessível de fora — e sem dados de terceiros (CPF, contato).
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
      Há um documento aguardando a sua assinatura pela <b>{{ $signer->directorLabel() }}</b>:
    </p>

    <p style="margin:0 0 18px;background:#f2ecea;border-radius:10px;padding:12px 16px;font-size:15px;line-height:1.5;">
      <b>{{ $document->title }}</b><br>
      <span style="font-size:13px;color:#6d6062;">
        Gerado por {{ $document->created_by_name ?? 'não registrado' }}
        @if($expiresAt)
          · assine até {{ $expiresAt->format('d/m/Y \à\s H:i') }}
        @endif
      </span>
    </p>

    <p style="font-size:15px;line-height:1.6;margin:0 0 14px;">
      Para assinar, vá ao tablet de assinatura do clube, toque em <b>Assinatura da Diretoria</b> e entre com a sua
      <b>matrícula e o PIN</b> do Lara. A lista mostra todos os documentos pendentes de sua assinatura.
    </p>

    <p style="font-size:13px;line-height:1.6;color:#6d6062;margin:0 0 6px;">
      Ainda não tem PIN? Cadastre no seu Perfil, no Lara.
    </p>

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Você recebeu esta mensagem porque foi indicado(a) para assinar este documento pela diretoria do clube.
    </p>
  </div>
</body>
</html>
