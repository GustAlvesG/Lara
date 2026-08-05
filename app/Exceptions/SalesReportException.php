<?php

namespace App\Exceptions;

use Exception;

/**
 * Falha ao apurar as vendas no MultiVendas — parâmetro inválido ou o banco
 * fora de alcance. Vira 422/502 no kiosk, com a mensagem indo para a tela: quem
 * está no balcão precisa saber se pode informar o valor manualmente.
 */
class SalesReportException extends Exception
{
}
