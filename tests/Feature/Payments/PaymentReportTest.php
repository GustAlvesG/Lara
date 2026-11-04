<?php

namespace Tests\Feature\Payments;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Schedule;
use App\Models\SchedulePayment;
use App\Services\SchedulePaymentReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * Pagamentos (Reservas): filtro por período e extração em CSV.
 *
 * O período vale pela data do pagamento (padrão) ou pela do horário; o CSV
 * sai com uma linha por horário (grupo, local, data, início e fim) e o valor
 * pago só na primeira linha do pagamento, para a soma na planilha fechar.
 */
class PaymentReportTest extends TestCase
{
    use CreatesSocialBookingSchema;

    private Place $court;
    private Place $court2;
    private int $ana;
    private int $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSocialBookingSchema();

        if (!Schema::hasTable('status')) {
            Schema::create('status', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('portuguese')->nullable();
            });
        }
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'Confirmed', 'portuguese' => 'Confirmado'],
            ['id' => 2, 'name' => 'Cancelled', 'portuguese' => 'Cancelado'],
        ]);

        $tenis = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        $this->court = Place::create(['name' => 'Quadra 1', 'place_group_id' => $tenis->id, 'status_id' => 1, 'price' => 80]);
        $this->court2 = Place::create(['name' => 'Quadra 2', 'place_group_id' => $tenis->id, 'status_id' => 1, 'price' => 60]);

        $this->ana = DB::table('members')->insertGetId(['cpf' => '39053344705', 'name' => 'Ana', 'email' => 'ana@teste.local', 'title' => '1001']);
        $this->bruno = DB::table('members')->insertGetId(['cpf' => '86288366757', 'name' => 'Bruno', 'email' => 'bruno@teste.local', 'title' => '1002']);
    }

    private function pagamento(int $member, string $paidAt, array $slots, float $amount): SchedulePayment
    {
        $payment = SchedulePayment::create([
            'payment_method' => 'credit', 'paid_amount' => $amount, 'paid_at' => $paidAt, 'status_id' => 1,
            'payment_integration_id' => 'tid-' . $paidAt,
        ]);

        foreach ($slots as [$place, $start]) {
            Schedule::create([
                'place_id' => $place->id, 'member_id' => $member, 'start_schedule' => $start,
                'end_schedule' => date('Y-m-d H:i:s', strtotime($start) + 3600), 'status_id' => 1,
                'price' => $place->price, 'schedule_payment_id' => $payment->id,
            ]);
        }

        return $payment;
    }

    private function report(): SchedulePaymentReport
    {
        return app(SchedulePaymentReport::class);
    }

    public function test_periodo_pela_data_do_pagamento(): void
    {
        $outubro = $this->pagamento($this->ana, '2026-10-30 10:00:00', [[$this->court, '2026-11-05 18:00:00']], 80);
        $novembro = $this->pagamento($this->bruno, '2026-11-02 23:30:00', [[$this->court2, '2026-11-03 08:00:00']], 60);

        $ids = $this->report()->query(new Request(['date_from' => '2026-11-01', 'date_to' => '2026-11-02']))->pluck('id')->all();

        // O pagamento das 23:30 do último dia entra: o "até" é o dia inteiro.
        $this->assertSame([$novembro->id], $ids);
        $this->assertNotContains($outubro->id, $ids);
    }

    public function test_periodo_pela_data_do_horario(): void
    {
        $usaEmNovembro = $this->pagamento($this->ana, '2026-10-30 10:00:00', [[$this->court, '2026-11-05 18:00:00']], 80);
        $this->pagamento($this->bruno, '2026-11-02 09:00:00', [[$this->court2, '2026-11-20 08:00:00']], 60);

        $ids = $this->report()->query(new Request([
            'period_by' => 'use', 'date_from' => '2026-11-01', 'date_to' => '2026-11-10',
        ]))->pluck('id')->all();

        $this->assertSame([$usaEmNovembro->id], $ids);
    }

    public function test_csv_uma_linha_por_horario_com_local_e_hora(): void
    {
        $payment = $this->pagamento($this->ana, '2026-11-02 10:00:00', [
            [$this->court2, '2026-11-06 19:00:00'],
            [$this->court, '2026-11-06 18:00:00'],
        ], 140);

        $linhas = iterator_to_array($this->report()->rows(new Request()), false);

        $this->assertSame(SchedulePaymentReport::HEADER, $linhas[0]);
        $this->assertCount(3, $linhas);

        $col = array_flip(SchedulePaymentReport::HEADER);
        [$primeira, $segunda] = [$linhas[1], $linhas[2]];

        $this->assertSame((string) $payment->id, $primeira[$col['Pagamento']]);
        $this->assertSame('Ana', $primeira[$col['Sócio']]);
        $this->assertSame('Cartão de crédito', $primeira[$col['Forma']]);
        $this->assertSame('2', $primeira[$col['Horários no pagamento']]);

        // Em ordem de horário, com grupo, local, data, início e fim.
        $this->assertSame(['Tênis', 'Quadra 1', '06/11/2026', '18:00', '19:00', '80,00'], array_slice($primeira, $col['Grupo'], 6));
        $this->assertSame(['Tênis', 'Quadra 2', '06/11/2026', '19:00', '20:00', '60,00'], array_slice($segunda, $col['Grupo'], 6));

        // Valor pago só na primeira linha: somar a coluna dá o total certo.
        $this->assertSame('140,00', $primeira[$col['Valor pago']]);
        $this->assertSame('', $segunda[$col['Valor pago']]);
        $this->assertSame('Confirmado', $primeira[$col['Status do horário']]);
    }

    public function test_csv_respeita_os_filtros_da_tela(): void
    {
        $this->pagamento($this->ana, '2026-11-02 10:00:00', [[$this->court, '2026-11-06 18:00:00']], 80);
        $this->pagamento($this->bruno, '2026-11-02 11:00:00', [[$this->court2, '2026-11-06 18:00:00']], 60);

        $linhas = iterator_to_array($this->report()->rows(new Request(['member' => 'Bruno'])), false);

        $this->assertCount(2, $linhas);
        $this->assertSame('Bruno', $linhas[1][array_flip(SchedulePaymentReport::HEADER)['Sócio']]);
    }

    public function test_nome_do_arquivo_leva_o_periodo(): void
    {
        $this->assertSame('pagamentos-reservas-2026-11-01_a_2026-11-30.csv',
            $this->report()->filename(new Request(['date_from' => '2026-11-01', 'date_to' => '2026-11-30'])));
        $this->assertSame('pagamentos-reservas.csv', $this->report()->filename(new Request(['date_from' => 'lixo'])));
    }
}
