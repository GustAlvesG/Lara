<?php

namespace App\Services\PoliBot;

use App\Models\BotAttendanceEvent as Evento;
use App\Models\BotSession;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * O diário dos atendimentos do O Lara: o que chegou a ele, o que saiu dele e
 * o que foi encerrado, cada um com o motivo. É o que a tela "Transferências e
 * encerramentos" mostra.
 *
 * Duas fontes:
 *
 *   - O Lara anota o que ele mesmo faz, ANTES de pedir à Poli (transbordo,
 *     encerramento, forward para si). Antes porque a mensagem de sistema que
 *     a Poli manda de volta pode chegar enquanto o pedido ainda está no ar.
 *   - O webhook (observe) traz o resto: a mensagem de sistema confirma a
 *     anotação pendente, ou vira uma linha própria quando foi a Poli ou um
 *     atendente quem agiu.
 *
 * Os eventos de sistema da Poli dizem quem agiu sem precisar ler o texto
 * (medido em 15–16/10/2026):
 *
 *   - ATTENDANCE_REDIRECTED com `direction = EMPTY` é a Poli sozinha
 *     ("pelo sistema": bot da Poli, distribuição do time); o autor é o novo
 *     atendente;
 *   - com `direction = SYSTEM` e autor com uuid, um atendente transferiu;
 *     sem uuid, foi a API — O Lara;
 *   - ATTENDANCE_CLOSED: autor com uuid é o atendente que encerrou; sem
 *     uuid, a API.
 *
 * Nada aqui lança: o diário é registro, e não pode derrubar o webhook nem
 * a conversa. Erro vira log.
 */
class AttendanceJournal
{
    /** Anotação do O Lara que espera a mensagem de sistema da Poli por até isto. */
    private const PENDING_MINUTES = 5;

    private const POLI = 'Poli (sistema)';
    private const O_LARA = 'O Lara';

    /* ---------------------------------------------------------------------
     | O que O Lara faz
     |---------------------------------------------------------------------*/

    /**
     * O distribute para um time, antes de ele sair, com a foto de quem do
     * time estava disponível naquele momento (TeamAvailability::snapshot).
     *
     * @param array{size: int, online: int, available: int, members: array}|null $time
     */
    public function handoffStarted(BotSession $session, ?string $teamUuid, string $reason, ?array $time = null, ?string $detail = null): ?Evento
    {
        return $this->write($session->contact_uuid, $session, [
            'kind' => Evento::ENVIADA,
            'reason' => $reason,
            'detail' => $detail,
            'actor' => self::O_LARA,
            'target' => $teamUuid,
            'outcome' => Evento::AGUARDANDO,
            'team_size' => $time['size'] ?? null,
            'team_online' => $time['online'] ?? null,
            'team_available' => $time['available'] ?? null,
            'team_snapshot' => $time['members'] ?? null,
        ]);
    }

    /** O distribute voltou: recusado ou sem time, a linha já sabe o resultado. */
    public function handoffSent(?Evento $evento, bool $aceito, ?string $teamUuid): void
    {
        if (!$aceito) {
            $this->update($evento, [
                'outcome' => Evento::FALHOU,
                'outcome_detail' => $teamUuid === null ? 'sem time configurado' : 'distribute recusado (ver poli_messages)',
            ]);
        }
    }

    /** O que a conferência do transbordo (ConfirmPoliBotHandoff) viu na API. */
    public function handoffChecked(string $contactUuid, string $outcome, ?string $detail = null): void
    {
        $this->update($this->pending($contactUuid, Evento::ENVIADA, minutes: 10), [
            'outcome' => $outcome,
            'outcome_detail' => $detail,
        ]);
    }

    /** O close, antes de ele sair. */
    public function closing(string $contactUuid, string $reason, ?BotSession $session = null, ?string $detail = null): ?Evento
    {
        return $this->write($contactUuid, $session, [
            'kind' => Evento::ENCERRAMENTO,
            'reason' => $reason,
            'detail' => $detail,
            'actor' => self::O_LARA,
        ]);
    }

    public function closed(?Evento $evento, bool $aceito): void
    {
        if (!$aceito) {
            $this->update($evento, ['outcome' => Evento::FALHOU, 'outcome_detail' => 'close recusado (ver o log)']);
        }
    }

    /** O forward do contato para o próprio O Lara (resgate, aviso do carro). */
    public function forwarding(string $contactUuid, string $reason, ?BotSession $session = null): ?Evento
    {
        return $this->write($contactUuid, $session, [
            'kind' => Evento::RECEBIDA,
            'reason' => $reason,
            'actor' => self::O_LARA,
            'target' => self::O_LARA,
        ]);
    }

    public function forwarded(?Evento $evento, bool $aceito): void
    {
        if (!$aceito) {
            $this->update($evento, ['outcome' => Evento::FALHOU, 'outcome_detail' => 'forward recusado (ver o log)']);
        }
    }

    /**
     * A transferência para O Lara desta mensagem de sistema foi a Poli
     * devolvendo um transbordo que ele acabou de fazer? Quem decide é observe(),
     * que roda no webhook antes de o job da transferência existir.
     */
    public function isReturnedHandoff(string $poliMessageId): bool
    {
        try {
            return Evento::where('poli_message_id', $poliMessageId)
                ->where('kind', Evento::RECEBIDA)
                ->where('reason', 'retorno_transbordo')
                ->exists();
        } catch (Throwable $e) {
            Log::warning('PoliBot: diário ilegível', ['erro' => $e->getMessage()]);

            return false;
        }
    }

    /* ---------------------------------------------------------------------
     | O que a Poli conta pelo webhook
     |---------------------------------------------------------------------*/

    /**
     * Transferência e encerramento. `$session` é a sessão como estava ANTES
     * de o evento mudá-la — é por ela que se sabe se O Lara conduzia a conversa.
     */
    public function observe(array $payload, ?BotSession $session): void
    {
        try {
            $this->observeEvent($payload, $session);
        } catch (Throwable $e) {
            Log::warning('PoliBot: evento fora do diário', ['erro' => $e->getMessage()]);
        }
    }

    private function observeEvent(array $payload, ?BotSession $session): void
    {
        $value = $payload['value'] ?? null;
        $tipo = is_array($value) ? ($value['type'] ?? null) : null;

        if (!in_array($tipo, ['ATTENDANCE_REDIRECTED', 'ATTENDANCE_CLOSED'], true)) {
            return;
        }

        $contato = $value['contact']['uuid'] ?? null;
        $mensagem = $value['metadata']['external_message_id'] ?? $value['uuid'] ?? null;

        if (!is_string($contato) || $contato === '') {
            return;
        }

        // A Poli reenvia o mesmo evento: a primeira volta já está no diário.
        if (is_string($mensagem) && Evento::where('poli_message_id', $mensagem)->exists()) {
            return;
        }

        $atendimento = is_array($value['attendance'] ?? null) ? $value['attendance'] : [];
        $poli = array_filter([
            'poli_message_id' => is_string($mensagem) ? $mensagem : null,
            'poli_text' => $this->string($value['components']['body']['text'] ?? null),
            'attendance_uuid' => $this->string($atendimento['uuid'] ?? null),
            'contact_phone' => $this->string($value['contact']['attributes']['phone'] ?? null),
            'contact_name' => $this->string($value['contact']['attributes']['name'] ?? null),
        ], fn ($v) => $v !== null);

        $tipo === 'ATTENDANCE_CLOSED'
            ? $this->observeClosed($contato, $value, $atendimento, $poli)
            : $this->observeRedirected($contato, $value, $atendimento, $poli, $session);
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $atendimento
     * @param array<string, string> $poli
     */
    private function observeRedirected(string $contato, array $value, array $atendimento, array $poli, ?BotSession $session): void
    {
        $destino = $this->string($atendimento['attendant']['uuid'] ?? null);
        $destinoNome = $this->string($atendimento['attendant']['attributes']['name'] ?? null) ?? $destino;
        $autorUuid = $this->string($value['author']['uuid'] ?? null);
        $autorNome = $this->string($value['author']['attributes']['name'] ?? null);

        // EMPTY = "pelo sistema"; SYSTEM = alguém pediu (atendente ou API).
        $pedida = strtoupper((string) ($value['direction'] ?? '')) === 'SYSTEM';
        $porAtendente = $pedida && $autorUuid !== null && !$this->isLara($autorUuid);
        $transbordo = $this->pending($contato, Evento::ENVIADA, minutes: $this->returnWindowMinutes());

        if ($this->isLara($destino)) {
            if ($porAtendente) {
                $this->write($contato, null, [...$poli, 'kind' => Evento::RECEBIDA, 'reason' => 'atendente', 'actor' => $autorNome ?? $autorUuid, 'target' => self::O_LARA]);

                return;
            }

            if ($pedida && ($forward = $this->pending($contato, Evento::RECEBIDA)) !== null) {
                $this->update($forward, $poli);

                return;
            }

            if (!$pedida && $transbordo !== null) {
                $this->update($transbordo, ['outcome' => Evento::DEVOLVIDO, 'outcome_detail' => self::O_LARA]);

                // Nova tentativa da fila voltando: o resultado na própria
                // tentativa basta — uma linha "recebida" a cada 3 minutos
                // só enterraria o resto do diário.
                if ($transbordo->reason === 'fila_de_espera') {
                    return;
                }

                $this->write($contato, null, [
                    ...$poli,
                    'kind' => Evento::RECEBIDA,
                    'reason' => 'retorno_transbordo',
                    'detail' => 'Transbordo das ' . $transbordo->occurred_at?->format('H:i:s'),
                    'actor' => self::POLI,
                    'target' => self::O_LARA,
                    'flow_slug' => $transbordo->flow_slug,
                    'step_key' => $transbordo->step_key,
                ]);

                return;
            }

            $this->write($contato, null, [
                ...$poli,
                'kind' => Evento::RECEBIDA,
                'reason' => $pedida ? 'api' : 'entrada',
                'actor' => $pedida ? self::O_LARA : self::POLI,
                'target' => self::O_LARA,
            ]);

            return;
        }

        if ($destino === null) {
            return;
        }

        // O transbordo do O Lara chegou a alguém.
        if ($transbordo !== null) {
            $this->update($transbordo, ['outcome' => Evento::ATENDENTE, 'outcome_detail' => $destinoNome]);

            return;
        }

        // Saiu do O Lara no meio da conversa, sem ele pedir. Transferência
        // entre atendentes não é assunto do diário.
        if ($session !== null && $session->lara_owned && ($session->inFlow() || $session->isEnding())) {
            $this->write($contato, $session, [
                ...$poli,
                'kind' => Evento::ENVIADA,
                'reason' => 'retirada',
                'actor' => $porAtendente ? ($autorNome ?? $autorUuid) : self::POLI,
                'target' => $destinoNome,
                'outcome' => Evento::ATENDENTE,
                'outcome_detail' => $destinoNome,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $value
     * @param array<string, mixed> $atendimento
     * @param array<string, string> $poli
     */
    private function observeClosed(string $contato, array $value, array $atendimento, array $poli): void
    {
        $motivoPoli = $this->string($atendimento['closed_reason'] ?? null);

        if (($fechamento = $this->pending($contato, Evento::ENCERRAMENTO)) !== null) {
            $this->update($fechamento, $poli);

            return;
        }

        $autorUuid = $this->string($value['author']['uuid'] ?? null);
        $autorTipo = $this->string($value['author']['type'] ?? null);

        if ($autorUuid !== null && !$this->isLara($autorUuid)) {
            $quem = $this->string($value['author']['attributes']['name'] ?? null)
                ?? $this->nameInText($poli['poli_text'] ?? null)
                ?? $this->string($atendimento['attendant']['attributes']['name'] ?? null)
                ?? $autorUuid;

            $this->write($contato, null, [...$poli, 'kind' => Evento::ENCERRAMENTO, 'reason' => 'atendente', 'detail' => $motivoPoli, 'actor' => $quem]);

            return;
        }

        $pelaApi = $autorTipo === 'USER' || $this->isLara($autorUuid);

        $this->write($contato, null, [
            ...$poli,
            'kind' => Evento::ENCERRAMENTO,
            'reason' => $pelaApi ? 'api' : 'sistema',
            'detail' => $motivoPoli,
            'actor' => $pelaApi ? self::O_LARA : self::POLI,
        ]);
    }

    /* ---------------------------------------------------------------------
     | Apoio
     |---------------------------------------------------------------------*/

    /**
     * A anotação do O Lara mais recente deste contato ainda à espera da Poli:
     * transbordo sem resultado, forward ou close sem mensagem de sistema.
     */
    private function pending(string $contato, string $kind, int $minutes = self::PENDING_MINUTES): ?Evento
    {
        $q = Evento::where('contact_uuid', $contato)
            ->where('kind', $kind)
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->orderByDesc('id');

        $kind === Evento::ENVIADA
            ? $q->where('outcome', Evento::AGUARDANDO)
            : $q->whereNull('poli_message_id')->where('actor', self::O_LARA)
                ->where(fn ($w) => $w->whereNull('outcome')->orWhere('outcome', '!=', Evento::FALHOU));

        return $q->first();
    }

    /**
     * Até quando a volta "pelo sistema" para O Lara conta como devolução do
     * transbordo. Medido em 16/10/2026: ~1 s depois do distribute.
     */
    private function returnWindowMinutes(): int
    {
        return max(1, (int) ceil((int) config('poli.bot.handoff_return_seconds', 120) / 60));
    }

    /** @param array<string, mixed> $campos */
    private function write(string $contato, ?BotSession $session, array $campos): ?Evento
    {
        try {
            return Evento::create([
                'occurred_at' => now(),
                'contact_uuid' => $contato,
                'contact_phone' => $session?->contact_phone,
                'contact_name' => $session?->contact_name,
                'attendance_uuid' => $session?->attendance_uuid,
                'flow_slug' => $session?->flow_slug,
                'step_key' => $session?->step_key,
                ...array_filter($campos, fn ($v) => $v !== null),
            ]);
        } catch (Throwable $e) {
            Log::warning('PoliBot: não deu para anotar no diário', ['contact_uuid' => $contato, 'erro' => $e->getMessage()]);

            return null;
        }
    }

    /** @param array<string, mixed> $campos */
    private function update(?Evento $evento, array $campos): void
    {
        if ($evento === null) {
            return;
        }

        try {
            // O que a anotação do O Lara já sabia (atendimento, contato) fica.
            foreach (['attendance_uuid', 'contact_phone', 'contact_name'] as $campo) {
                if (filled($evento->{$campo})) {
                    unset($campos[$campo]);
                }
            }

            $evento->update($campos);
        } catch (Throwable $e) {
            Log::warning('PoliBot: não deu para atualizar o diário', ['evento' => $evento->id, 'erro' => $e->getMessage()]);
        }
    }

    private function isLara(?string $uuid): bool
    {
        $lara = config('poli.bot.user_uuid');

        return $uuid !== null && is_string($lara) && $lara !== '' && $uuid === $lara;
    }

    /** "Chat finalizado por Fulano em 16/10/2026 às 12:06" → Fulano. */
    private function nameInText(?string $texto): ?string
    {
        return $texto !== null && preg_match('/ por (.+?) em \d{2}\/\d{2}\/\d{4}/u', $texto, $m) ? trim($m[1]) : null;
    }

    private function string(mixed $valor): ?string
    {
        return is_string($valor) && trim($valor) !== '' ? $valor : null;
    }
}
