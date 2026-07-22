<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Trava de segurança: a suíte só roda contra SQLite.
     *
     * Os testes usam RefreshDatabase, que apaga todas as tabelas do banco
     * configurado. Se o isolamento do phpunit.xml for removido ou sobrescrito,
     * `php artisan test` passa a rodar contra o banco do .env — o de
     * homologação — e destrói os dados. Já aconteceu duas vezes, então a
     * proteção mora aqui também, e não só na configuração.
     *
     * Roda depois de o app subir e antes de RefreshDatabase agir.
     */
    protected function setUpTraits(): array
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            $database = config("database.connections.{$connection}.database");

            throw new RuntimeException(
                "Suíte abortada: os testes apagariam o banco '{$database}' (conexão '{$connection}', driver '{$driver}'). "
                . 'Os testes só podem rodar em SQLite. Confira se DB_CONNECTION=sqlite e DB_DATABASE=:memory: '
                . 'continuam ativos no phpunit.xml e se nenhuma variável de ambiente os está sobrescrevendo.'
            );
        }

        return parent::setUpTraits();
    }
}
