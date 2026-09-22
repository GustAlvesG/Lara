@php
    /**
     * Página pública de validação. Layout próprio, sem o menu do sistema:
     * quem chega aqui não está logado, chegou pelo QR do papel.
     *
     * Mostra pouco de propósito — ver o comentário do ValidationController.
     */
    use App\Models\SignatureDocument;

    $conferencia = $conferencia ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Validação de documento — CFCSN</title>
  <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
@verbatim
  <style>
    :root{
      --bg:#efe9e8; --surface:#fff; --border:#e8dedd; --border-strong:#d8cbc9;
      --ink:#1f1819; --ink-2:#6d6062; --brand:#A00001;
      --ok:#157a58; --ok-tint:#e0f2ea; --warn:#a3560a; --warn-tint:#fbedd9;
      --bad:#b3261e; --bad-tint:#fbe7e5;
      --sans: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    }
    *{box-sizing:border-box;}
    body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--sans);line-height:1.55;}
    .wrap{max-width:720px;margin:0 auto;padding:28px 18px 60px;}
    header{display:flex;align-items:center;gap:12px;margin-bottom:22px;}
    .dot{width:44px;height:44px;border-radius:13px;background:var(--brand);color:#fff;display:grid;place-items:center;font-weight:800;}
    header b{display:block;font-size:16px;}
    header span{font-size:13px;color:var(--ink-2);}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px;margin-bottom:16px;}
    h1{font-size:22px;margin:0 0 6px;}
    p.sub{margin:0 0 18px;color:var(--ink-2);font-size:14.5px;}
    .badge{display:inline-block;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:800;}
    .b-ok{background:var(--ok-tint);color:var(--ok);}
    .b-warn{background:var(--warn-tint);color:var(--warn);}
    .b-bad{background:var(--bad-tint);color:var(--bad);}
    dl{margin:16px 0 0;}
    dt{font-size:12px;color:var(--ink-2);text-transform:uppercase;letter-spacing:.4px;margin-top:12px;}
    dd{margin:2px 0 0;font-size:15px;font-weight:600;}
    table{width:100%;border-collapse:collapse;margin-top:10px;}
    th,td{text-align:left;padding:8px 6px;border-bottom:1px solid var(--border);font-size:14px;}
    th{font-size:11.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--ink-2);}
    .hash{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;word-break:break-all;color:var(--ink-2);}
    .file{display:block;margin:12px 0;font-size:14px;}
    button{min-height:48px;padding:0 22px;border:none;border-radius:14px;background:var(--brand);color:#fff;font-weight:800;font-size:15px;cursor:pointer;font-family:inherit;}
    .note{border-radius:14px;padding:14px 16px;font-size:14px;margin-top:14px;}
    .n-ok{background:var(--ok-tint);color:var(--ok);}
    .n-warn{background:var(--warn-tint);color:var(--warn);}
    .n-bad{background:var(--bad-tint);color:var(--bad);}
    footer{font-size:12.5px;color:var(--ink-2);text-align:center;margin-top:26px;}
    .err{color:var(--bad);font-size:13.5px;margin-top:8px;}
  </style>
@endverbatim
</head>
<body>
<div class="wrap">

  <header>
    <div class="dot">CF</div>
    <div>
      <b>Clube dos Funcionários da CSN</b>
      <span>Validação de documento assinado eletronicamente</span>
    </div>
  </header>

  @if(!$document)
    <div class="card">
      <h1>Documento não encontrado</h1>
      <p class="sub">
        Não há documento com o código <b>{{ $codigo }}</b>. Confira se o código foi digitado
        corretamente — ele tem 12 caracteres e não usa as letras O, I e L nem os números 0 e 1.
      </p>
    </div>
  @else
    <div class="card">
      @if($document->status === SignatureDocument::STATUS_FINALIZED)
        <span class="badge b-ok">Documento autêntico e assinado</span>
      @elseif($document->status === SignatureDocument::STATUS_SIGNED)
        <span class="badge b-ok">Assinado — via final em preparação</span>
      @elseif($document->status === SignatureDocument::STATUS_AWAITING_SIGNATURE)
        <span class="badge b-warn">Emitido, ainda sem assinatura</span>
      @else
        <span class="badge b-bad">{{ $document->statusLabel() }}</span>
      @endif

      <h1 style="margin-top:14px;">{{ $document->title }}</h1>
      <p class="sub">Código de validação <b>{{ $document->validation_code }}</b></p>

      <dl>
        <dt>Emitido em</dt>
        <dd>{{ $document->frozen_at?->format('d/m/Y \à\s H:i') }}</dd>

        @if($document->finalized_at)
          <dt>Concluído em</dt>
          <dd>{{ $document->finalized_at->format('d/m/Y \à\s H:i') }}</dd>
        @endif

        <dt>Local do atendimento</dt>
        <dd>{{ $document->location ?? 'não informado' }}</dd>
      </dl>

      <dt style="margin-top:18px;">Signatários</dt>
      <table>
        <thead>
          <tr><th>Nome</th><th>CPF</th><th>Situação</th><th>Assinatura</th></tr>
        </thead>
        <tbody>
          @foreach($document->publicSigners() as $signatario)
            <tr>
              <td>{{ $signatario['name'] }}</td>
              <td>{{ $signatario['cpf'] }}</td>
              <td>{{ $signatario['status'] }}</td>
              <td>{{ $signatario['signed_at'] ?? '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="card">
      <h1 style="font-size:18px;">Confira o arquivo</h1>
      <p class="sub">
        Envie o PDF que você recebeu. Ele é lido apenas para calcular a impressão digital (SHA-256)
        e comparar com a registrada — o arquivo não é guardado.
      </p>

      <form method="POST" action="{{ route('signature.validate.verify', $codigo) }}" enctype="multipart/form-data">
        @csrf
        <input class="file" type="file" name="documento" accept="application/pdf" required>
        @error('documento')<div class="err">{{ $message }}</div>@enderror
        <button type="submit">Conferir arquivo</button>
      </form>

      @if($conferencia)
        @if($conferencia['resultado'] === 'final')
          <div class="note n-ok">
            <b>Confere.</b> Este arquivo é exatamente a via assinada deste documento.
          </div>
        @elseif($conferencia['resultado'] === 'original')
          <div class="note n-warn">
            <b>É o documento original, antes das assinaturas.</b> O conteúdo é o que foi apresentado
            para assinar, mas esta não é a via final — peça a via assinada ao clube.
          </div>
        @else
          <div class="note n-bad">
            <b>Não confere.</b> Este arquivo não corresponde a nenhuma versão registrada deste documento.
            Ele pode ter sido alterado, ou ser de outro documento.
          </div>
        @endif

        <p class="hash" style="margin-top:10px;">
          Impressão digital do arquivo enviado:<br>{{ $conferencia['hash'] }}
        </p>
      @endif

      <dl>
        <dt>Impressão digital do documento original</dt>
        <dd class="hash">{{ $document->original_sha256 ?? '—' }}</dd>

        @if($document->final_sha256)
          <dt>Impressão digital da via assinada</dt>
          <dd class="hash">{{ $document->final_sha256 }}</dd>
        @endif
      </dl>
    </div>
  @endif

  <footer>
    Esta página confirma a existência e a integridade do documento. Para obter uma via,
    procure o atendimento do clube.
  </footer>
</div>
</body>
</html>
