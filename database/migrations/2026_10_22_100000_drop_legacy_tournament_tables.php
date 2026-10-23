<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Apaga as tabelas do primeiro módulo de torneios (2026_02_23_133450_tourments),
 * que nunca chegou ao menu e foi substituído por um módulo novo, com outra
 * modelagem (docs/funcionalidades/torneios.md).
 *
 * Conferido em 22/10/2026: as sete tabelas estavam vazias em homologação
 * (lara_hml) e em produção (lara).
 *
 * A ordem respeita as FKs: filhas primeiro. `dropIfExists` deixa rodar de novo
 * sem erro.
 */
return new class extends Migration
{
    private const TABLES = [
        'tournament_subscription_payment',
        'tournament_subscription',
        'team_member',
        'teams',
        'tournaments_categories',
        'categories',
        'tournaments',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Sem volta de propósito: o módulo novo cria a sua própria `tournaments`,
     * e recriar a antiga aqui colidiria com ela. Quem precisar do esquema
     * antigo o encontra na migration de 2026_02_23.
     */
    public function down(): void
    {
    }
};
