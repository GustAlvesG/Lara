<?php

namespace App\Services\MultiVendas;

use Illuminate\Support\Carbon;

/**
 * Filtro do painel Vendas: período de/até (datas inclusivas) e lojas (vazio =
 * todas). Já validado pelo SalesDashboardService.
 */
final class SalesFilter
{
    /**
     * @param  list<string>  $stores
     */
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly array $stores = [],
    ) {
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function cacheKey(): string
    {
        return 'dashboard:vendas:' . $this->from->toDateString() . ':' . $this->to->toDateString() . ':' . md5(implode('|', $this->stores));
    }
}
