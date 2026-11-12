<?php

namespace App\Services\MultiVendas;

use RuntimeException;

/** O servidor do MultiVendas não respondeu (ou a view não existe lá). */
class SalesDashboardUnavailable extends RuntimeException
{
}
