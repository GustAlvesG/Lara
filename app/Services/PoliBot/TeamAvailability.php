<?php

namespace App\Services\PoliBot;

use App\Services\Poli\PoliClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Quem do time estava disponível no momento de um transbordo — a "foto" que
 * vai para o diário (bot_attendance_events.team_*), dê o transbordo certo ou
 * não. É a prova de por que a Poli devolveu a conversa para O Lara.
 *
 * Disponível = `status` ONLINE e `status_of_service` AVAILABLE na Poli
 * (GET /teams/{uuid}?include=users, medido em 19/10/2026). Se é exatamente
 * esse o critério da distribuição da Poli não está documentado: a foto
 * guarda os dois campos de cada membro para conferir depois.
 *
 * Não lança: sem a Poli, o transbordo segue sem foto.
 */
class TeamAvailability
{
    public function __construct(private readonly PoliClient $poli) {}

    /**
     * @return array{size: int, online: int, available: int, members: list<array{uuid: ?string, name: ?string, status: ?string, service: ?string}>}|null
     */
    public function snapshot(?string $teamUuid): ?array
    {
        if ($teamUuid === null || $teamUuid === '') {
            return null;
        }

        try {
            $membros = $this->poli->membrosDoTime($teamUuid);
        } catch (Throwable $e) {
            Log::warning('PoliBot: transbordo sem a foto do time', ['team_uuid' => $teamUuid, 'erro' => $e->getMessage()]);

            return null;
        }

        $lista = array_map(fn (array $m) => [
            'uuid' => is_string($m['uuid'] ?? null) ? $m['uuid'] : null,
            'name' => is_string($m['attributes']['name'] ?? null) ? $m['attributes']['name'] : null,
            'status' => is_string($m['status'] ?? null) ? $m['status'] : null,
            'service' => is_string($m['status_of_service'] ?? null) ? $m['status_of_service'] : null,
        ], $membros);

        return [
            'size' => count($lista),
            'online' => count(array_filter($lista, fn ($m) => $m['status'] === 'ONLINE')),
            'available' => count(array_filter($lista, fn ($m) => self::isAvailable($m))),
            'members' => $lista,
        ];
    }

    /** @param array{status: ?string, service: ?string} $membro */
    public static function isAvailable(array $membro): bool
    {
        return $membro['status'] === 'ONLINE' && $membro['service'] === 'AVAILABLE';
    }
}
