<?php

namespace App\Services\EmailCampaign;

use App\Exceptions\EmailCampaignException;
use App\Exceptions\SpreadsheetImportException;
use App\Models\EmailCampaign\EmailCampaign;
use App\Models\EmailCampaign\EmailCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sobe a lista de destinatários para a campanha.
 *
 * Não é tudo-ou-nada como as importações de cadastro: numa lista de milhares
 * de e-mails sempre há linha suja, e recusar tudo por uma delas só atrasaria
 * o envio. O que não serve (e-mail inválido, repetido, acima do teto) fica de
 * fora e volta contado para a tela, com as primeiras linhas recusadas.
 *
 * Célula com vários endereços ("a@x.com; b@y.com") vira um destinatário para
 * cada, com o mesmo nome e as mesmas colunas.
 */
final class RecipientImporter
{
    private const CHUNK = 500;

    private const SAMPLE_LIMIT = 20;

    public function __construct(private readonly RecipientListReader $reader)
    {
    }

    /**
     * @param  bool  $replace  true apaga a lista atual antes; false acrescenta
     * @return array{added: int, duplicates: int, invalid: int, over_limit: int, samples: list<string>, total: int}
     *
     * @throws SpreadsheetImportException|EmailCampaignException
     */
    public function import(EmailCampaign $campaign, string $path, string $extension, string $originalName, bool $replace): array
    {
        if (! $campaign->isEditable()) {
            throw new EmailCampaignException('A lista só muda com a campanha em rascunho ou pausada.');
        }

        // Pausada, parte da lista já recebeu: trocar a lista apagaria esse
        // histórico e mandaria de novo para quem voltasse no arquivo novo.
        if ($replace && $campaign->status !== EmailCampaign::STATUS_DRAFT) {
            throw new EmailCampaignException('Com a campanha pausada, a lista só pode ser acrescentada, não substituída.');
        }

        $list = $this->reader->read($path, $extension);

        if ($list['rows'] === []) {
            throw new SpreadsheetImportException('A lista não tem nenhuma linha preenchida.');
        }

        return DB::transaction(function () use ($campaign, $list, $originalName, $replace) {
            if ($replace) {
                $campaign->recipients()->delete();
            }

            $room = max(0, (int) config('email_campaigns.max_recipients') - $campaign->recipients()->count());
            $summary = ['added' => 0, 'duplicates' => 0, 'invalid' => 0, 'over_limit' => 0, 'samples' => []];
            $seen = [];
            $buffer = [];
            $now = now();

            foreach ($list['rows'] as $row) {
                $data = $row['data'];
                $addresses = preg_split('/[\s;,]+/', mb_strtolower($data['email'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);

                if ($addresses === []) {
                    $this->reject($summary, $row['line'], 'sem e-mail');

                    continue;
                }

                $name = Str::limit(trim($data['nome'] ?? ''), 250, '') ?: null;
                $extra = array_diff_key($data, ['email' => true, 'nome' => true]);

                foreach ($addresses as $address) {
                    if (! $this->valid($address)) {
                        $this->reject($summary, $row['line'], '"' . Str::limit($address, 80) . '" não é um e-mail válido');

                        continue;
                    }

                    if (isset($seen[$address])) {
                        $summary['duplicates']++;

                        continue;
                    }

                    $seen[$address] = true;

                    if (count($buffer) + $summary['added'] >= $room) {
                        $summary['over_limit']++;

                        continue;
                    }

                    $buffer[] = [
                        'email_campaign_id' => $campaign->id,
                        'email' => $address,
                        'name' => $name,
                        'data' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                        'status' => EmailCampaignRecipient::STATUS_PENDING,
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($buffer) >= self::CHUNK) {
                        $this->flush($buffer, $summary);
                    }
                }
            }

            $this->flush($buffer, $summary);

            $columns = array_diff_key($list['columns'], ['email' => true]);

            $campaign->forceFill([
                'columns' => $replace ? $columns : ($campaign->columns ?? []) + $columns,
                'list_name' => $replace || ! $campaign->list_name
                    ? $originalName
                    : Str::limit($campaign->list_name . ', ' . $originalName, 250),
            ])->save();

            return $summary + ['total' => $campaign->recipients()->count()];
        });
    }

    /**
     * Grava o lote; o índice único (campanha, e-mail) descarta quem já estava
     * na lista — no "acrescentar", é o repetido entre arquivos.
     */
    private function flush(array &$buffer, array &$summary): void
    {
        if ($buffer === []) {
            return;
        }

        $inserted = DB::table('email_campaign_recipients')->insertOrIgnore($buffer);

        $summary['added'] += $inserted;
        $summary['duplicates'] += count($buffer) - $inserted;
        $buffer = [];
    }

    private function reject(array &$summary, int $line, string $reason): void
    {
        $summary['invalid']++;

        if (count($summary['samples']) < self::SAMPLE_LIMIT) {
            $summary['samples'][] = "Linha {$line}: {$reason}";
        }
    }

    /** O filtro do PHP aceita "a@b"; quem entrega exige domínio com ponto. */
    private function valid(string $address): bool
    {
        return strlen($address) <= 254
            && filter_var($address, FILTER_VALIDATE_EMAIL) !== false
            && str_contains(substr($address, strrpos($address, '@') + 1), '.');
    }
}
