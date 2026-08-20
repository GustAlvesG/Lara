<?php

namespace App\Console\Commands;

use App\Models\Placar\ApiCliente;
use App\Support\Placar\PlacarAbilities;
use Illuminate\Console\Command;

/**
 * Gera o token de acesso pessoal (Sanctum) que o Node usa para consumir a API
 * do Placar Clube — cola em LARAVEL_API_TOKEN no .env dele.
 *
 * O nome identifica o cliente (ex.: "node-producao"), não o token em si: uma
 * segunda chamada com o mesmo nome reaproveita o mesmo `ApiCliente` e emite
 * um token novo — útil para rotacionar sem revogar o anterior na hora.
 */
class PlacarTokenCommand extends Command
{
    protected $signature = 'placar:token {nome : Identifica o cliente, ex.: node-producao}';

    protected $description = 'Gera um token Sanctum (ability placar:operar) para o Node consumir a API do Placar Clube';

    public function handle(): int
    {
        $nome = trim((string) $this->argument('nome'));

        if ($nome === '') {
            $this->error('Informe um nome para identificar o cliente (ex.: node-producao).');
            return self::FAILURE;
        }

        $cliente = ApiCliente::firstOrCreate(['nome' => $nome]);

        $tokensAnteriores = $cliente->tokens()->count();
        if ($tokensAnteriores > 0) {
            $this->warn("\"{$nome}\" já tem {$tokensAnteriores} token(s) ativo(s). Este comando NÃO os revoga — o novo token convive com eles.");
        }

        $token = $cliente->createToken($nome, [PlacarAbilities::OPERAR]);

        $this->info('Token gerado com sucesso. Configure no .env do Node:');
        $this->newLine();
        $this->line("LARAVEL_API_TOKEN={$token->plainTextToken}");
        $this->newLine();
        $this->comment('Este valor não fica salvo em lugar nenhum — se perder, gere outro com o mesmo nome.');

        return self::SUCCESS;
    }
}
