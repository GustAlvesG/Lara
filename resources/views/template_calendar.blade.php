<!DOCTYPE html>

<html>
    <head>
    <title>Calendário de Agendamentos Diário - {{ $date }}</title>
    <!-- O CSS interno é essencial para o Dompdf -->
    <style>
    body {
    font-family: 'Arial', sans-serif;
    margin: 0;
    padding: 0;
    color: #444;
    }
    .container {
    padding: 20px;
    }
    h1 {
    color: #1a5c88;
    border-bottom: 2px solid #a8c1d3;
    padding-bottom: 5px;
    margin-bottom: 10px;
    font-size: 20px;
    }
    .date-info {
    font-size: 14px;
    margin-bottom: 20px;
    font-weight: bold;
    color: #666;
    }

        /* Estilos para Agrupamento */
        .group-container {
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        .group-header-main {
            background-color: #1a5c88; /* Azul escuro */
            color: white;
            padding: 8px 15px;
            font-size: 16px;
            font-weight: bold;
        }
        .group-header-sub {
            background-color: #f1f7fa; /* Cinza/Azul claro */
            color: #1a5c88;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
            border-top: 1px solid #e0e0e0;
        }

        /* Estilos da Tabela */
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }
        .schedule-table th, .schedule-table td {
            padding: 8px 15px;
            text-align: left;
            font-size: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .schedule-table th {
            background-color: #c0d3e0; /* Tom mais suave para o cabeçalho */
            color: #1a5c88;
            font-weight: bold;
            text-transform: uppercase;
        }
        .schedule-table tbody tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        .time, .price {
            white-space: nowrap;
            width: 10%;
        }
        .price {
            text-align: right;
            font-weight: bold;
        }
        .status-cell {
            width: 15%;
            font-weight: bold;
        }
        
        /* Cores de Status (Exemplo - Adicione mais se necessário) */
        .status-confirmed { color: #27ae60; } /* Verde para confirmado */
        .status-pending { color: #f39c12; } /* Amarelo para pendente */
        .status-cancelled { color: #e74c3c; } /* Vermelho para cancelado */
        
        /* Estilos para o Rodapé */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #aaa;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>


    </head>
    <body>
    <div class="container">
    <h1>CALENDÁRIO DE AGENDAMENTOS DIÁRIO</h1>
    <div class="date-info">
    Período: Agendamentos para o dia {{ $date }}
    </div>


        @foreach ($groupedSchedules as $groupName => $places)
            <div class="group-container">
                <div class="group-header-main">
                    {{ $groupName }}
                </div>

                @foreach ($places as $placeName => $schedules)
                    <div class="group-header-sub">
                        LOCAL: {{ $placeName }}
                    </div>

                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th class="time">Início</th>
                                <th class="time">Fim</th>
                                <th>Cliente (Membro)</th>
                                <th class="price">Preço</th>
                                <th class="status-cell">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedules as $schedule)
                                @php
                                    // Determina a classe de cor baseada no nome do status (ex: "confirmado")
                                    $statusSlug = strtolower($schedule->status->portuguese ?? 'desconhecido');
                                    $statusClass = '';
                                    if (str_contains($statusSlug, 'confirma')) {
                                        $statusClass = 'status-confirmed';
                                    } elseif (str_contains($statusSlug, 'pendente')) {
                                        $statusClass = 'status-pending';
                                    } elseif (str_contains($statusSlug, 'cancela')) {
                                        $statusClass = 'status-cancelled';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $schedule->start_schedule->format('H:i') }}</td>
                                    <td>{{ $schedule->end_schedule->format('H:i') }}</td>
                                    <td>{{ $schedule->member->name ?? 'Membro Não Encontrado' }}</td>
                                    <td class="price">R$ {{ number_format($schedule->price, 2, ',', '.') }}</td>
                                    <td>
                                        <span class="{{ $statusClass }}">
                                            {{ ucfirst($schedule->status->portuguese) ?? 'N/A' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        @endforeach

        <div class="footer">
            Relatório gerado em {{ Carbon\Carbon::now()->format('d/m/Y H:i:s') }}
            
            
        </div>
    </div>


    </body>
</html>