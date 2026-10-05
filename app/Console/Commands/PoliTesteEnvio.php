<?php

namespace App\Console\Commands;

use App\Services\Poli\PoliClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Teste de envio REAL pela API Poli (smoke test de homologação).
 * Espelha o poli_teste_envio.py (validado em produção) — mesmas requisições.
 *
 * Exemplos:
 *   php artisan poli:teste-envio 24999998888
 *   php artisan poli:teste-envio 24999998888 -m "Olá do bot"
 *   php artisan poli:teste-envio <contact_uuid> --contato
 *   php artisan poli:teste-envio --templates --tipo=BUTTON
 *   php artisan poli:teste-envio 24999998888 --template=<uuid> --param=Gustavo --param=25/09
 *   php artisan poli:teste-envio <contact_uuid> --encerrar -m "Atendimento encerrado. Obrigado!"
 */
class PoliTesteEnvio extends Command
{
    protected $signature = 'poli:teste-envio
        {destino? : Telefone (ex.: 24999998888) ou contact_uuid (com --contato / --encerrar)}
        {--m|mensagem= : Texto da mensagem (ou despedida, com --encerrar)}
        {--contato : O destino é um contact_uuid}
        {--template= : UUID de um template (BUTTON/LIST/WABA) para enviar em vez de texto}
        {--param=* : Variável do template (repita na ordem)}
        {--templates : Lista os templates cadastrados}
        {--tipo= : Filtro do --templates: BUTTON, LIST, WABA, QUICK_MESSAGE}
        {--encerrar : Encerra a conversa do contact_uuid informado}
        {--aguardar=30 : Segundos acompanhando o status (ACK); 0 para não acompanhar}
        {--force : Não pedir confirmação}';

    protected $description = 'Envia mensagem/template real via Poli, lista templates ou encerra conversa';

    private const FINAL_OK = ['RECEIVED_BY_CLIENT', 'READ_BY_CLIENT', 'AUDIO_LISTENED'];

    public function handle(PoliClient $poli): int
    {
        $faltando = collect(['base_url', 'token', 'account_uuid', 'channel_uuid'])
            ->filter(fn ($k) => blank(config("poli.$k")));

        if ($faltando->isNotEmpty()) {
            $this->error('Configuração ausente: poli.'.$faltando->implode(', poli.'));
            $this->line('Verifique o .env (POLI_*) e rode php artisan config:clear.');

            return self::FAILURE;
        }

        try {
            if ($this->option('templates')) {
                return $this->listarTemplates($poli);
            }

            $destino = $this->argument('destino');
            if (! $destino) {
                $this->error('Informe o destino (ou use --templates).');

                return self::FAILURE;
            }

            if ($this->option('encerrar')) {
                return $this->encerrar($poli, $destino);
            }

            return $this->enviar($poli, $destino);
        } catch (RequestException $e) {
            $this->error('Falha HTTP '.$e->response->status());
            $this->line($e->response->body());
            $this->dica($e->response->status());

            return self::FAILURE;
        } catch (ConnectionException $e) {
            $this->error('Sem conexão com a Poli: '.$e->getMessage());

            return self::FAILURE;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function listarTemplates(PoliClient $poli): int
    {
        $resp = $poli->templates($this->option('tipo'));
        $lista = $resp['data'] ?? $resp;

        if (empty($lista)) {
            $this->warn('Nenhum template encontrado.');

            return self::SUCCESS;
        }

        foreach ($lista as $t) {
            $this->newLine();
            $this->info(sprintf('%s  [%s]  %s  (%s)', $t['uuid'] ?? '?', $t['type'] ?? '?', $t['key'] ?? '', $t['status'] ?? ''));
            $msg = $t['message'] ?? null;

            if (is_array($msg)) {
                $this->line('   texto : '.mb_strimwidth(str_replace("\n", ' ', $msg['body'] ?? ''), 0, 90, '…'));
                foreach ($msg['buttons'] ?? [] as $b) {
                    $this->line('   botão : '.($b['text'] ?? ''));
                }
                if (! empty($msg['button'])) {
                    $this->line('   abre  : '.$msg['button']);
                }
                foreach ($msg['section'] ?? $msg['sections'] ?? [] as $sec) {
                    foreach ($sec['rows'] ?? [] as $row) {
                        $this->line('   item  : '.(data_get($row, 'messageOption.title') ?? $row['title'] ?? ''));
                    }
                }
            } elseif ($msg) {
                $this->line('   texto : '.mb_strimwidth(str_replace("\n", ' ', (string) $msg), 0, 90, '…'));
            }
        }

        return self::SUCCESS;
    }

    private function enviar(PoliClient $poli, string $destino): int
    {
        $porContato = (bool) $this->option('contato');
        $template = $this->option('template');
        $params = $this->option('param');
        $texto = $this->option('mensagem')
            ?: 'Teste de envio Lara ↔ Poli em '.now()->format('d/m/Y H:i:s').' ✅';

        $this->table(['Item', 'Valor'], [
            ['Ambiente', app()->environment()],
            ['Base URL', config('poli.base_url')],
            ['Token', substr((string) config('poli.token'), 0, 4).'…'],
            ['Canal', config('poli.channel_uuid')],
            ['Destino', ($porContato ? 'contact_uuid ' : 'telefone ').$destino],
            $template
                ? ['Template', $template.($params ? ' ('.implode(', ', $params).')' : '')]
                : ['Texto', $texto],
        ]);

        if (! $this->option('force') && ! $this->confirm('Isto envia uma mensagem REAL no WhatsApp. Continuar?')) {
            return self::SUCCESS;
        }

        $inicio = microtime(true);

        $resp = match (true) {
            $template && $porContato => $poli->template($destino, $template, $params),
            (bool) $template         => $poli->templatePorTelefone($destino, $template, $params),
            $porContato              => $poli->texto($destino, $texto),
            default                  => $poli->textoPorTelefone($destino, $texto),
        };

        $this->info('Enviado em '.(int) ((microtime(true) - $inicio) * 1000).' ms');
        $this->line(json_encode($resp, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($contato = data_get($resp, 'contact.uuid')) {
            $this->line("contact_uuid: {$contato}  (use para --encerrar)");
        }

        $uuid = $resp['uuid'] ?? null;
        if (! $uuid) {
            $this->warn('A resposta não trouxe uuid — não dá para acompanhar o status.');

            return self::FAILURE;
        }

        return $this->acompanhar($poli, $uuid);
    }

    private function encerrar(PoliClient $poli, string $contactUuid): int
    {
        if (preg_match('/^[\d()+\s-]+$/', $contactUuid)) {
            $this->error('--encerrar precisa do contact_uuid, não do telefone.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Encerrar a conversa {$contactUuid}?")) {
            return self::SUCCESS;
        }

        $poli->encerrar($contactUuid, $this->option('mensagem') ?: null);
        $this->info('✔ Conversa encerrada.');

        return self::SUCCESS;
    }

    private function acompanhar(PoliClient $poli, string $uuid): int
    {
        $aguardar = (int) $this->option('aguardar');
        if ($aguardar <= 0) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("Acompanhando status por até {$aguardar}s…");

        $fim = now()->addSeconds($aguardar);
        $ultimo = null;

        while (now()->lt($fim)) {
            try {
                $msg = $poli->mensagem($uuid);
            } catch (RequestException $e) {
                $this->warn('Não consegui consultar a mensagem (HTTP '.$e->response->status().'). Confira pelo webhook de ACK.');

                return self::SUCCESS;
            }

            $ack = data_get($msg, 'ack') ?? data_get($msg, 'data.ack');

            if ($ack !== $ultimo) {
                $this->line('  '.now()->format('H:i:s').'  '.($ack ?? '(sem campo ack)'));
                $ultimo = $ack;
            }

            if (in_array($ack, self::FINAL_OK, true)) {
                $this->info('✔ Entregue ao cliente.');

                return self::SUCCESS;
            }

            if ($ack === 'ERROR') {
                $this->error('✖ A Poli/provedor reportou ERROR no envio.');
                $this->line('Causas comuns: fora da janela de 24h (WABA exige template), número sem WhatsApp, canal desconectado.');

                return self::FAILURE;
            }

            sleep(3); // ~10 consultas em 30s, dentro do limite de 60/min
        }

        $this->warn("Tempo esgotado com status {$ultimo}.");

        return self::SUCCESS;
    }

    private function dica(int $status): void
    {
        $this->line(match ($status) {
            401     => 'Dica: token inválido/expirado ou header errado (use Authorization: Bearer).',
            403     => 'Dica: o app/token não tem permissão nessa conta.',
            404     => 'Dica: contact_uuid, account_uuid, template ou canal inexistente.',
            422     => 'Dica: dados inválidos — confira account_channel_uuid, template_uuid e o número.',
            429     => 'Dica: rate limit (60 req/min por aplicação). Aguarde 1 minuto.',
            default => 'Dica: erro do lado da Poli; tente novamente e registre o horário.',
        });
    }
}
