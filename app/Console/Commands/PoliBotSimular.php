<?php

namespace App\Console\Commands;

use App\Models\BotSession;
use App\Models\PoliMessage;
use App\Services\Poli\ParsedPoliMessage;
use App\Services\PoliBot\BotEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Conversa com o bot sem WhatsApp.
 *
 * Força o modo sombra e esvazia o piloto durante a execução: NADA sai para a
 * Poli e nenhum pedido de Uber é criado — as respostas do bot aparecem aqui e
 * ficam em poli_messages com shadow = true. O estado da conversa fica em
 * bot_sessions, então dá para seguir mandando mensagens em chamadas
 * separadas, como no WhatsApp.
 *
 *   php artisan poli:bot-simular oi
 *   php artisan poli:bot-simular 4
 *   php artisan poli:bot-simular "ABC1D23"
 *   php artisan poli:bot-simular --imagem=https://exemplo/print.jpg
 *   php artisan poli:bot-simular --reset
 */
class PoliBotSimular extends Command
{
    protected $signature = 'poli:bot-simular
        {texto? : Mensagem do contato}
        {--imagem= : Em vez de texto, simula uma imagem com esta URL}
        {--contato=simulador : contact_uuid da conversa simulada}
        {--reset : Apaga a conversa simulada e começa do zero}';

    protected $description = 'Simula uma conversa com o bot do WhatsApp (modo sombra, nada é enviado)';

    public function handle(BotEngine $bot): int
    {
        $contato = (string) $this->option('contato');

        if ($this->option('reset')) {
            BotSession::where('contact_uuid', $contato)->delete();
            PoliMessage::where('contact_uuid', $contato)->delete();
            $this->info("Conversa \"{$contato}\" apagada.");

            if (!$this->argument('texto') && !$this->option('imagem')) {
                return self::SUCCESS;
            }
        }

        $texto = $this->argument('texto');
        $imagem = $this->option('imagem');

        if (!$texto && !$imagem) {
            $this->error('Informe o texto da mensagem (ou --imagem=URL).');

            return self::FAILURE;
        }

        // Nada sai daqui, seja qual for o .env.
        config(['poli.bot.mode' => BotEngine::MODE_SHADOW, 'poli.bot.live_contacts' => []]);

        $ultimo = (int) PoliMessage::where('contact_uuid', $contato)->max('id');

        $bot->handleInbound(new ParsedPoliMessage(
            messageId: 'sim-' . Str::ulid(),
            contactUuid: $contato,
            contactPhone: null,
            contactName: 'Simulador|Teste',
            attendanceUuid: 'sim-atendimento',
            type: $imagem ? ParsedPoliMessage::TYPE_IMAGE : ParsedPoliMessage::TYPE_TEXT,
            text: $imagem ? null : (string) $texto,
            mediaUrl: $imagem ?: null,
        ));

        $respostas = PoliMessage::where('contact_uuid', $contato)
            ->where('id', '>', $ultimo)
            ->where('direction', PoliMessage::OUT)
            ->orderBy('id')
            ->get();

        $this->line('<comment>você:</comment> ' . ($imagem ? "[imagem {$imagem}]" : $texto));

        if ($respostas->isEmpty()) {
            $this->line('<comment>bot:</comment>  (silêncio)');
        }

        foreach ($respostas as $r) {
            $this->line('<info>bot:</info>  ' . str_replace("\n", "\n      ", (string) $r->texto));

            if ($r->type === 'TEMPLATE' && $r->options) {
                foreach ($r->options as $i => $o) {
                    $this->line('      ' . ($i + 1) . ') ' . ($o['label'] ?? ''));
                }
            }
        }

        $sessao = BotSession::find($contato);
        $this->newLine();
        $this->line(sprintf(
            '<comment>estado:</comment> %s  fluxo=%s  passo=%s  tentativas=%d  dados=%s',
            $sessao?->state ?? '-',
            $sessao?->flow_slug ?? '-',
            $sessao?->step_key ?? '-',
            $sessao?->tentativas ?? 0,
            json_encode($sessao?->data ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));

        return self::SUCCESS;
    }
}
