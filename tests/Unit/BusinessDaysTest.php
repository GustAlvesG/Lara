<?php

namespace Tests\Unit;

use App\Support\BusinessDays;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** Prazo em dias úteis: segunda a sexta, contando a partir do dia seguinte. */
class BusinessDaysTest extends TestCase
{
    private function deadline(string $from, int $days = 2): string
    {
        return BusinessDays::deadline(Carbon::parse($from), $days)->format('Y-m-d H:i:s');
    }

    public function test_no_meio_da_semana(): void
    {
        $this->assertSame('2026-10-16 23:59:59', $this->deadline('2026-10-14 10:00')); // qua → sex
    }

    public function test_sexta_pula_o_fim_de_semana(): void
    {
        $this->assertSame('2026-10-20 23:59:59', $this->deadline('2026-10-16 18:00')); // sex → ter
    }

    public function test_pedido_no_fim_de_semana_conta_de_segunda(): void
    {
        $this->assertSame('2026-10-20 23:59:59', $this->deadline('2026-10-17 09:00')); // sáb → ter
        $this->assertSame('2026-10-20 23:59:59', $this->deadline('2026-10-18 09:00')); // dom → ter
    }

    public function test_um_dia_util(): void
    {
        $this->assertSame('2026-10-19 23:59:59', $this->deadline('2026-10-16 08:00', 1)); // sex → seg
    }
}
