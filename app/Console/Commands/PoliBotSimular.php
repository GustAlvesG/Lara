<?php

namespace App\Console\Commands;

use App\Services\PoliBot\BotSimulator;
use Illuminate\Console\Command;

/**
 * Conversa com o bot sem WhatsApp — a mesma simulação da tela.
 *
 * Nada sai para a Poli e nenhum pedido de Uber é criado, seja qual for o
 * .env (ver BotSimulator). O estado fica em bot_sessions, então dá para
 * seguir mandando mensagens em chamadas separadas, como no WhatsApp.
 *
 *   php artisan poli:bot-simular oi
 *   php artisan poli:bot-simular 4
 *   php artisan poli:bot-simular --imagem=https://exemplo/print.jpg
 *   php artisan poli:bot-simular --fluxo=carro-de-aplicativo   # começa por ele, mesmo inativo
 *   php artisan poli:bot-simular --reset
 */
class PoliBotSimular extends Command
{
    protected $signature = 'poli:bot-simular
        {texto? : Mensagem do contato}
        {--imagem= : Em vez de texto, simula uma imagem com esta URL}
        {--fluxo= : Começa a conversa por este fluxo (slug), mesmo inativo}
        {--contato=simulador : contact_uuid da conversa simulada}
        {--reset : Apaga a conversa simulada e começa do zero}';

    protected $description = 'Simula uma conversa com o bot do WhatsApp (modo sombra, nada é enviado)';

    public function handle(BotSimulator $simulador): int
    {
        $contato = (string) $this->option('contato');
        $texto = $this->argument('texto');
        $imagem = $this->option('imagem');
        $fluxo = $this->option('fluxo');

        if ($this->option('reset')) {
            $simulador->reset($contato);
            $this->info("Conversa \"{$contato}\" apagada.");
        }

        if ($fluxo) {
            $this->mostrar($simulador->start($contato, (string) $fluxo), "[começar pelo fluxo {$fluxo}]");
        }

        if ($texto || $imagem) {
            $this->mostrar($simulador->send($contato, $texto, $imagem), $imagem ? "[imagem {$imagem}]" : (string) $texto);
        } elseif (!$fluxo && !$this->option('reset')) {
            $this->error('Informe o texto da mensagem (ou --imagem=URL, --fluxo=slug).');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function mostrar(array $resultado, string $entrada): void
    {
        $this->line('<comment>você:</comment> ' . $entrada);

        if ($resultado['replies'] === []) {
            $this->line('<comment>bot:</comment>  (silêncio)');
        }

        foreach ($resultado['replies'] as $r) {
            $this->line('<info>bot:</info>  ' . str_replace("\n", "\n      ", (string) $r['text']));

            if ($r['type'] === 'TEMPLATE' && $r['options']) {
                foreach ($r['options'] as $i => $o) {
                    $this->line('      ' . ($i + 1) . ') ' . ($o['label'] ?? ''));
                }
            }
        }

        $s = $resultado['session'];
        $this->newLine();
        $this->line(sprintf(
            '<comment>estado:</comment> %s  fluxo=%s  passo=%s  tentativas=%d  dados=%s',
            $s['state'] ?? '-',
            $s['flow'] ?? '-',
            $s['step'] ?? '-',
            $s['tentativas'] ?? 0,
            json_encode($s['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }
}
