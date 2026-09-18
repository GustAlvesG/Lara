<?php

namespace App\Support\Replay;

/**
 * Ability única do Sanctum para o sistema de captura das quadras. Um só ponto
 * de verdade para o nome — usado no comando `replay:token` e no grupo de
 * rotas de `routes/api.php`.
 *
 * Não há granularidade menor porque é sempre o mesmo sistema falando com a
 * API: ele lê a configuração das câmeras e envia os clipes.
 */
final class ReplayAbilities
{
    const OPERATE = 'replay:operate';
}
