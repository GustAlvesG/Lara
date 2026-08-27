<?php

namespace App\Support\Placar;

/**
 * Ability única do Sanctum para a integração com o Node. Um só ponto de
 * verdade para o nome — usado no comando `placar:token` e no grupo de rotas
 * de `routes/api.php`.
 */
final class PlacarAbilities
{
    const OPERAR = 'placar:operar';
}
