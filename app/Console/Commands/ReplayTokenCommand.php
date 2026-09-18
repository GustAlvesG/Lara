<?php

namespace App\Console\Commands;

use App\Models\Replay\ApiClient;
use App\Support\Replay\ReplayAbilities;
use Illuminate\Console\Command;

/**
 * Gera o token Sanctum que o sistema de captura das quadras usa para
 * consumir a API do Replay.
 *
 * O nome identifica o CLIENTE (ex.: "captura-producao"), não o token: rodar
 * de novo com o mesmo nome reaproveita o mesmo ApiClient e emite um token
 * novo, sem revogar o anterior — que é como se troca a chave sem derrubar a
 * gravação das quadras no meio do expediente.
 */
class ReplayTokenCommand extends Command
{
    protected $signature = 'replay:token {nome : Identifica o cliente, ex.: captura-producao}';

    protected $description = 'Gera um token Sanctum (ability replay:operate) para o sistema de captura consumir a API do Replay';

    public function handle(): int
    {
        $nome = trim((string) $this->argument('nome'));

        if ($nome === '') {
            $this->error('Informe um nome para identificar o cliente (ex.: captura-producao).');

            return self::FAILURE;
        }

        $client = ApiClient::firstOrCreate(['name' => $nome]);

        $existing = $client->tokens()->count();
        if ($existing > 0) {
            $this->warn("\"{$nome}\" já tem {$existing} token(s) ativo(s). Este comando NÃO os revoga — o novo convive com eles.");
        }

        $token = $client->createToken($nome, [ReplayAbilities::OPERATE]);

        $this->info('Token gerado. Configure no .env do sistema de captura:');
        $this->newLine();
        $this->line("REPLAY_API_TOKEN={$token->plainTextToken}");
        $this->newLine();
        $this->comment('Este valor não fica salvo em lugar nenhum — se perder, gere outro com o mesmo nome.');

        return self::SUCCESS;
    }
}
