<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Move a mídia do Placar que ficou em `storage/app/public/placar` para
 * `public/storage/placar`, onde o servidor web realmente a alcança.
 *
 * Contexto: até a correção do armazenamento, o módulo gravava no disco
 * `public` do Laravel, que depende do symlink de `storage:link`. Este projeto
 * nunca teve esse symlink — `public/storage` é um diretório real, com
 * arquivos de outras áreas do sistema — então toda logo/foto enviada pelo
 * Placar era gravada num lugar que nenhuma URL alcançava, e o link quebrava.
 *
 * Idempotente: rodar de novo com a origem vazia não faz nada. Nunca
 * sobrescreve um arquivo já existente no destino (avisa e mantém os dois),
 * e só apaga a origem depois de confirmar que a cópia chegou.
 */
class PlacarMigrarMidiaCommand extends Command
{
    protected $signature = 'placar:migrar-midia {--dry-run : Só lista o que seria movido, sem mover nada}';

    protected $description = 'Move a mídia do Placar de storage/app/public/placar para public/storage/placar';

    public function handle(): int
    {
        $origem = storage_path('app/public/placar');
        $destino = public_path('storage/placar');

        if (!File::isDirectory($origem)) {
            $this->info('Nada a migrar: ' . $origem . ' não existe.');
            return self::SUCCESS;
        }

        $arquivos = File::allFiles($origem);

        if ($arquivos === []) {
            $this->info('Nada a migrar: nenhum arquivo em ' . $origem . '.');
            return self::SUCCESS;
        }

        $seco = (bool) $this->option('dry-run');
        $movidos = 0;
        $conflitos = 0;

        foreach ($arquivos as $arquivo) {
            $relativo = str_replace('\\', '/', $arquivo->getRelativePathname());
            $alvo = $destino . DIRECTORY_SEPARATOR . $arquivo->getRelativePathname();

            if (File::exists($alvo)) {
                $this->warn("já existe no destino, mantido intacto: placar/{$relativo}");
                $conflitos++;
                continue;
            }

            if ($seco) {
                $this->line("moveria: placar/{$relativo}");
                $movidos++;
                continue;
            }

            File::ensureDirectoryExists(dirname($alvo));

            // Copia e só então apaga a origem: se a cópia falhar no meio, o
            // arquivo original continua lá para uma nova tentativa.
            if (!File::copy($arquivo->getPathname(), $alvo)) {
                $this->error("falhou ao copiar: placar/{$relativo}");
                return self::FAILURE;
            }

            File::delete($arquivo->getPathname());
            $this->line("movido: placar/{$relativo}");
            $movidos++;
        }

        $this->newLine();
        $this->info($seco
            ? "{$movidos} arquivo(s) seriam movidos."
            : "{$movidos} arquivo(s) movidos para public/storage/placar.");

        if ($conflitos > 0) {
            $this->warn("{$conflitos} arquivo(s) já existiam no destino e não foram tocados — confira qual versão vale antes de apagar a origem.");
        }

        return self::SUCCESS;
    }
}
