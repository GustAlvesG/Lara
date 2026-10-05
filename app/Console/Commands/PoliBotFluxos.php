<?php

namespace App\Console\Commands;

use App\Models\BotFlow;
use App\Services\PoliBot\DefaultFlows;
use App\Services\PoliBot\FlowDefinition;
use Illuminate\Console\Command;

/**
 * Lista, valida e instala os fluxos do bot.
 *
 *   php artisan poli:bot-fluxos               # lista o que está no banco e valida
 *   php artisan poli:bot-fluxos --instalar    # grava os fluxos padrão que ainda não existem
 *   php artisan poli:bot-fluxos --instalar --sobrescrever   # regrava por cima (perde edições)
 */
class PoliBotFluxos extends Command
{
    protected $signature = 'poli:bot-fluxos
        {--instalar : Grava os fluxos padrão (DefaultFlows) que ainda não existem}
        {--sobrescrever : Com --instalar, regrava também os que já existem}';

    protected $description = 'Lista, valida e instala os fluxos do bot do WhatsApp';

    public function handle(): int
    {
        if ($this->option('instalar')) {
            $this->instalar();
        }

        $fluxos = BotFlow::orderBy('id')->get();

        if ($fluxos->isEmpty()) {
            $this->warn('Nenhum fluxo cadastrado. Use --instalar para gravar os padrões.');

            return self::SUCCESS;
        }

        $comErro = false;

        foreach ($fluxos as $fluxo) {
            $erros = $fluxo->flow()->errors();
            $comErro = $comErro || $erros !== [];

            $this->line(sprintf(
                '%s %s  <comment>%s</comment>  %d passos%s',
                $erros === [] ? '<info>✔</info>' : '<error>✖</error>',
                $fluxo->slug,
                $fluxo->name,
                count($fluxo->definition['steps'] ?? []),
                $fluxo->active ? '' : '  (inativo)',
            ));

            foreach ($erros as $erro) {
                $this->line("     - {$erro}");
            }
        }

        $this->newLine();
        $this->line('Modo do bot: <info>' . config('poli.bot.mode') . '</info> (POLI_BOT_MODE)');

        return $comErro ? self::FAILURE : self::SUCCESS;
    }

    private function instalar(): void
    {
        foreach (DefaultFlows::all() as $slug => $fluxo) {
            $erros = (new FlowDefinition($slug, $fluxo['definition']))->errors();

            if ($erros !== []) {
                $this->error("{$slug}: definição padrão inválida — " . implode(' ', $erros));

                continue;
            }

            $existente = BotFlow::where('slug', $slug)->first();

            if ($existente && !$this->option('sobrescrever')) {
                $this->line("• {$slug}: já existe, mantido.");

                continue;
            }

            BotFlow::updateOrCreate(['slug' => $slug], [
                'name' => $fluxo['name'],
                'active' => true,
                'definition' => $fluxo['definition'],
            ]);

            $this->info("• {$slug}: " . ($existente ? 'regravado.' : 'instalado.'));
        }

        $this->newLine();
    }
}
