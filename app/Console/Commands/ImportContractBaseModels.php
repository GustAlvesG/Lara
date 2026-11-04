<?php

namespace App\Console\Commands;

use App\Models\Sector;
use App\Models\SignatureContractClause;
use App\Models\SignatureContractModel;
use App\Services\Signature\Contracts\ContractBaseModels;
use Illuminate\Console\Command;

/**
 * Grava na biblioteca de Contratos os modelos-base e as cláusulas avulsas
 * tirados dos contratos reais do clube (ContractBaseModels), no setor
 * indicado — modelo e cláusula são por setor, e o contrato só oferece os do
 * próprio setor.
 *
 * Idempotente: o que já existe no setor com o mesmo nome (modelo) ou título
 * (cláusula) fica como está, a não ser com `--atualizar`. Não apaga nada.
 *
 *     php artisan contratos:modelos-base --setor=Comercial --setor=Manutenção
 */
class ImportContractBaseModels extends Command
{
    protected $signature = 'contratos:modelos-base
        {--setor=* : Setor (id ou nome) que recebe os modelos; repita para vários}
        {--atualizar : Regrava o conteúdo dos que já existem com o mesmo nome}
        {--sem-clausulas : Grava só os modelos, sem as cláusulas avulsas}
        {--listar : Só mostra o que seria gravado}';

    protected $description = 'Grava os modelos-base de contrato (tirados dos contratos do clube) na biblioteca de um ou mais setores';

    public function handle(): int
    {
        $modelos = ContractBaseModels::models();
        $clausulas = $this->option('sem-clausulas') ? [] : ContractBaseModels::clauses();

        if ($this->option('listar')) {
            $this->table(['Modelo', 'Cláusulas', 'Variáveis'], array_map(fn(array $m) => [
                $m['name'],
                count($m['content']['clauses']),
                count($m['content']['variables']),
            ], $modelos));
            $this->line(count($clausulas) . ' cláusulas avulsas: ' . implode(', ', array_column($clausulas, 'title')));

            return self::SUCCESS;
        }

        $pedidos = (array) $this->option('setor');

        if ($pedidos === []) {
            $this->error('Informe o setor: --setor=Comercial (repita para vários). Setores: '
                . Sector::orderBy('name')->pluck('name')->join(', '));

            return self::FAILURE;
        }

        $setores = [];

        foreach ($pedidos as $pedido) {
            $setor = ctype_digit((string) $pedido)
                ? Sector::find((int) $pedido)
                : Sector::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $pedido))])->first();

            if (!$setor) {
                $this->error("Setor não encontrado: {$pedido}.");

                return self::FAILURE;
            }

            $setores[$setor->id] = $setor;
        }

        $atualizar = (bool) $this->option('atualizar');

        foreach ($setores as $setor) {
            $criados = $atualizados = $mantidos = 0;

            foreach ($modelos as $m) {
                $existente = SignatureContractModel::where('sector_id', $setor->id)->where('name', $m['name'])->first();

                if ($existente && !$atualizar) {
                    $mantidos++;
                    continue;
                }

                if ($existente) {
                    $existente->update(['content' => $m['content'], 'sector_name' => $setor->name]);
                    $atualizados++;
                    continue;
                }

                SignatureContractModel::create([
                    'sector_id' => $setor->id,
                    'sector_name' => $setor->name,
                    'name' => $m['name'],
                    'content' => $m['content'],
                    'created_by' => null,
                    'created_by_name' => ContractBaseModels::AUTHOR,
                ]);
                $criados++;
            }

            foreach ($clausulas as $c) {
                $existente = SignatureContractClause::where('sector_id', $setor->id)->where('title', $c['title'])->first();

                if ($existente && !$atualizar) {
                    $mantidos++;
                    continue;
                }

                if ($existente) {
                    $existente->update(['category' => $c['category'], 'items' => $c['items'], 'updated_by_name' => ContractBaseModels::AUTHOR]);
                    $atualizados++;
                    continue;
                }

                SignatureContractClause::create([
                    'sector_id' => $setor->id,
                    'sector_name' => $setor->name,
                    'title' => $c['title'],
                    'category' => $c['category'],
                    'items' => $c['items'],
                    'created_by' => null,
                    'created_by_name' => ContractBaseModels::AUTHOR,
                ]);
                $criados++;
            }

            $this->info("{$setor->name}: {$criados} criados, {$atualizados} atualizados, {$mantidos} já existiam.");
        }

        return self::SUCCESS;
    }
}
