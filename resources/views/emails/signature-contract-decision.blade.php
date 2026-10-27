@php
    /**
     * Retorno da Gerência a quem redigiu: devolvido com observações ou
     * aprovado. Mesmo visual dos demais e-mails do módulo de assinatura.
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

    <p style="font-size:15px;line-height:1.6;margin:0 0 14px;">
      @if($approved)
        A Gerência <b>aprovou</b> o contrato:
      @else
        A Gerência <b>devolveu para ajustes</b> o contrato:
      @endif
    </p>

    <p style="margin:0 0 18px;background:#f2ecea;border-radius:10px;padding:12px 16px;font-size:15px;line-height:1.5;">
      <b>{{ $contract->number ? $contract->number . ' — ' : '' }}{{ $contract->title }}</b>
      @if($contract->counterpartyName())
        <br>{{ $contract->counterpartyName() }}
      @endif
      <br>
      <span style="font-size:13px;color:#6d6062;">
        Versão {{ $version->version }} · por {{ $version->decided_by_name ?? 'Gerência' }}
      </span>
    </p>

    @if($version->decision_note)
      <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">
        <b>Observação geral:</b><br>{{ $version->decision_note }}
      </p>
    @endif

    @if($approved)
      <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">
        O documento de assinatura foi gerado em <b>rascunho</b>: confira os signatários e os anexos e congele
        em Assinaturas → Documentos para colher as assinaturas.
      </p>
    @elseif($comments > 0)
      <p style="font-size:14px;line-height:1.6;margin:0 0 14px;">
        Há {{ $comments }} {{ $comments === 1 ? 'observação' : 'observações' }} nas cláusulas. Ajuste e envie de novo.
      </p>
    @endif

    <p style="margin:0 0 18px;">
      <a href="{{ $url }}" style="display:inline-block;background:#A00001;color:#fff;text-decoration:none;font-weight:bold;padding:11px 20px;border-radius:999px;font-size:14px;">
        Abrir o contrato no Lara
      </a>
    </p>

    <p style="font-size:12.5px;line-height:1.6;color:#6d6062;margin:18px 0 0;border-top:1px solid #e8dedd;padding-top:14px;">
      Você recebeu esta mensagem porque redigiu este contrato em Assinaturas → Contratos.
    </p>
  </div>
</body>
</html>
