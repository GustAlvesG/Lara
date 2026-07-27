<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regressão do incidente em que `php artisan test` rodou contra o banco de
 * homologação e o RefreshDatabase apagou todas as tabelas.
 *
 * Não usa RefreshDatabase de propósito: precisa poder rodar sozinho, sem
 * tocar em banco nenhum.
 */
class DatabaseIsolationTest extends TestCase
{
    public function test_a_suite_roda_isolada_em_sqlite_na_memoria(): void
    {
        $connection = config('database.default');

        $this->assertSame('sqlite', config("database.connections.{$connection}.driver"),
            'Os testes apagam o banco configurado. Eles só podem rodar em SQLite.');

        $this->assertSame(':memory:', config("database.connections.{$connection}.database"),
            'O banco de testes deve ser :memory:, definido no phpunit.xml.');
    }

    public function test_nao_aponta_para_o_banco_da_aplicacao(): void
    {
        $connection = config('database.default');

        $this->assertNotSame('mysql', $connection);
        $this->assertEmpty(config("database.connections.{$connection}.host"),
            'Um host preenchido significa banco de rede — os testes nunca devem alcançá-lo.');
    }
}
