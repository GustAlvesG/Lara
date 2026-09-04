<?php

namespace Tests\Unit;

use App\Console\Commands\ExpirePendingSchedules;
use App\Services\SchedulePricingService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Venda de horário já iniciado, cobrada proporcional ao tempo restante.
 *
 * A regra do balcão: no horário das 20:00 às 21:00, quem chega às 20:30 paga
 * metade. O corte não é o começo do horário (era, e por isso às 20:01 já não
 * dava para reservar) e sim o fim menos o hold do pagamento pendente — vender
 * mais tarde que isso criaria um pendente que só expiraria depois do horário.
 *
 * Sem banco: a regra é uma conta sobre o relógio e o preço do espaço.
 */
class ScheduleProportionalPriceTest extends TestCase
{
    private SchedulePricingService $pricing;

    /** Horário de referência: 20:00 às 21:00 de 04/09/2026, quadra de R$ 100. */
    private const START = '2026-09-04 20:00:00';
    private const END = '2026-09-04 21:00:00';
    private const BASE = 100.00;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = new SchedulePricingService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function priceAt(string $now): float
    {
        return $this->pricing->price(self::BASE, self::START, self::END, $now);
    }

    public function test_horario_que_ainda_nao_comecou_custa_o_preco_cheio(): void
    {
        $this->assertSame(100.00, $this->priceAt('2026-09-04 18:30:00'));
        $this->assertSame(100.00, $this->priceAt('2026-09-04 19:59:00'));
        $this->assertSame(100.00, $this->priceAt(self::START));
    }

    public function test_metade_do_horario_custa_metade_do_preco(): void
    {
        $this->assertSame(50.00, $this->priceAt('2026-09-04 20:30:00'));
    }

    /** Os segundos não entram na conta: às 20:30:47 ainda são 50%, não 49,98%. */
    public function test_segundos_nao_alteram_a_proporcao(): void
    {
        $this->assertSame(50.00, $this->priceAt('2026-09-04 20:30:47'));
    }

    public function test_proporcao_e_minuto_a_minuto(): void
    {
        $this->assertSame(75.00, $this->priceAt('2026-09-04 20:15:00'));
        $this->assertSame(38.33, $this->priceAt('2026-09-04 20:37:00'));
        $this->assertSame(25.00, $this->priceAt('2026-09-04 20:45:00'));
    }

    public function test_horario_encerrado_nao_vale_nada(): void
    {
        $this->assertSame(0.0, $this->priceAt(self::END));
        $this->assertSame(0.0, $this->priceAt('2026-09-04 21:30:00'));
    }

    public function test_minutos_restantes_acompanham_o_relogio(): void
    {
        $this->assertSame(60, $this->pricing->remainingMinutes(self::START, self::END, '2026-09-04 19:00:00'));
        $this->assertSame(30, $this->pricing->remainingMinutes(self::START, self::END, '2026-09-04 20:30:00'));
        $this->assertSame(0, $this->pricing->remainingMinutes(self::START, self::END, '2026-09-04 21:10:00'));
    }

    /**
     * O horário fica à venda depois de começado, mas fecha em `fim - hold`:
     * com hold de 10 minutos, 20:49 é o último minuto vendável.
     */
    public function test_venda_fecha_um_hold_antes_do_fim(): void
    {
        $this->assertSame(10, ExpirePendingSchedules::HOLD_MINUTES);

        $this->assertTrue($this->bookableAt('2026-09-04 20:01:00'));
        $this->assertTrue($this->bookableAt('2026-09-04 20:30:00'));
        $this->assertTrue($this->bookableAt('2026-09-04 20:49:00'));
        $this->assertTrue($this->bookableAt('2026-09-04 20:49:59'));

        $this->assertFalse($this->bookableAt('2026-09-04 20:50:00'));
        $this->assertFalse($this->bookableAt('2026-09-04 20:59:00'));
    }

    /** Antes de começar, a janela do hold não restringe nada. */
    public function test_horario_mais_curto_que_o_hold_continua_vendavel_antes_de_comecar(): void
    {
        $start = '2026-09-04 20:00:00';
        $end = '2026-09-04 20:05:00';

        $this->assertTrue($this->pricing->isBookable($start, $end, '2026-09-04 19:58:00'));
        $this->assertFalse($this->pricing->isBookable($start, $end, '2026-09-04 20:01:00'));
    }

    /** Sem relógio informado, vale o "agora" do sistema. */
    public function test_usa_o_relogio_do_sistema_quando_nao_recebe_referencia(): void
    {
        Carbon::setTestNow('2026-09-04 20:30:00');

        $this->assertSame(50.00, $this->pricing->price(self::BASE, self::START, self::END));
        $this->assertTrue($this->pricing->isBookable(self::START, self::END));
    }

    /** Reserva de cortesia (preço zero) segue zerada, sem divisão estranha. */
    public function test_preco_base_zero_continua_zero(): void
    {
        $this->assertSame(0.0, $this->pricing->price(0, self::START, self::END, '2026-09-04 20:30:00'));
    }

    /** Dados inconsistentes (fim antes do início) não viram desconto. */
    public function test_horario_sem_duracao_cobra_o_preco_cheio(): void
    {
        $this->assertSame(
            100.00,
            $this->pricing->price(self::BASE, self::START, self::START, '2026-09-04 20:30:00')
        );
    }

    private function bookableAt(string $now): bool
    {
        return $this->pricing->isBookable(self::START, self::END, $now);
    }
}
