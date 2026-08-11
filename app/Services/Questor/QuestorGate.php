<?php

namespace App\Services\Questor;

use App\Exceptions\QuestorException;
use Illuminate\Support\Facades\DB;

/**
 * As travas do módulo Questor, num lugar só.
 *
 * Duas perguntas se repetem em todo ponto de entrada — tela, comando, serviço:
 * "o módulo está ligado?" e "a gravação está liberada?". Respondê-las aqui
 * evita que uma nova tela nasça esquecendo uma delas, que é exatamente o tipo
 * de esquecimento que faria um UPDATE chegar ao ERP antes da hora.
 */
class QuestorGate
{
    /**
     * O módulo está configurado e ligado?
     */
    public static function enabled(): bool
    {
        return (bool) config('questor.enabled');
    }

    /**
     * Estamos em simulação? Ligado (o padrão), nenhuma escrita chega ao Questor.
     */
    public static function dryRun(): bool
    {
        return (bool) config('questor.dry_run', true);
    }

    /**
     * @throws QuestorException quando o módulo está desligado
     */
    public static function ensureEnabled(): void
    {
        if (!self::enabled()) {
            throw new QuestorException(
                'A integração com o Questor está desligada. '
                . 'Ligue QUESTOR_ENABLED no .env e confira as credenciais da conexão questor_sqlsrv.'
            );
        }
    }

    /**
     * Retrato da configuração, para a tela e para o comando de diagnóstico
     * dizerem em que pé está a integração sem cada um remontar isso do config.
     *
     * @return array{enabled: bool, dry_run: bool, connection: string, database: string,
     *               usuario_tecnico: int|null, filiais: array<int, int>, desde: string|null}
     */
    public static function summary(): array
    {
        return [
            'enabled' => self::enabled(),
            'dry_run' => self::dryRun(),
            'connection' => (string) config('questor.connection'),
            'database' => (string) config('questor.database'),
            'usuario_tecnico' => config('questor.usuario_tecnico'),
            'filiais' => (array) config('questor.filiais', []),
            'desde' => config('questor.desde'),
        ];
    }

    /**
     * Bate na conexão sem tocar em nada: um SELECT 1. Usado pelo diagnóstico e
     * pela tela, que prefere dizer "o ERP não respondeu" a mostrar uma fila
     * vazia que parece "não há nada para aprovar".
     *
     * @return array{ok: bool, erro: string|null}
     */
    public static function ping(): array
    {
        try {
            self::ensureEnabled();
            DB::connection(config('questor.connection'))->select('SELECT 1 AS ok');

            return ['ok' => true, 'erro' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'erro' => $e->getMessage()];
        }
    }
}
