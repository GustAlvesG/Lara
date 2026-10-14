{{-- Lista impressa de convidados não sócios para a portaria (dompdf: CSS
     simples, sem Tailwind). CPF completo: é o documento que a portaria confere. --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Convidados — reserva social #{{ $booking->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #475569; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; }
        td.check { width: 70px; }
        .foot { margin-top: 14px; font-size: 10px; color: #64748b; }
    </style>
</head>
<body>
    <h1>Convidados — {{ optional($booking->place)->name }}</h1>
    <div class="meta">
        {{ $booking->event_date->format('d/m/Y') }} · {{ $booking->event_type }} · reserva #{{ $booking->id }} ({{ $booking->stageLabel() }})<br>
        Sócio responsável: {{ optional($booking->member)->name ?? '—' }} {{ optional($booking->member)->title ? '— matrícula ' . $booking->member->title : '' }}<br>
        {{ $booking->guests->count() }} convidado(s) não sócio(s) de {{ $booking->guestLimit() }} permitidos.
    </div>
    <table>
        <thead><tr><th>#</th><th>Nome</th><th>CPF</th><th>Entrada</th></tr></thead>
        <tbody>
            @forelse ($booking->guests as $i => $guest)
                <tr><td>{{ $i + 1 }}</td><td>{{ $guest->name }}</td><td>{{ $guest->formattedCpf() }}</td><td class="check"></td></tr>
            @empty
                <tr><td colspan="4">Nenhum convidado na lista.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="foot">Sócios entram normalmente e não constam desta lista. Gerado pelo Lara em {{ now()->format('d/m/Y H:i') }}.</p>
</body>
</html>
