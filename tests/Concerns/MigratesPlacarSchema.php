<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;

/**
 * Migra só o que os testes do Placar Clube precisam — nunca a suíte de
 * migrations inteira: a cadeia completa quebra hoje no SQLite por um bug
 * pré-existente e fora de escopo (`members.title` adicionada duas vezes, em
 * `2024_05_16_115812_create_members_table.php` e
 * `2025_03_07_083719_add_columns_member.php` — ver commit da Etapa 6).
 *
 * Inclui as tabelas do Spatie Permission mesmo em testes que não usam
 * permissão nenhuma: o pacote registra um `Gate::before()` global que
 * consulta `permissions` para QUALQUER ability (inclusive as nossas,
 * `manage-placar-cadastro`/`view-placar-scout`) — sem a tabela, mesmo
 * vazia, o middleware `can:` das rotas web quebra.
 */
trait MigratesPlacarSchema
{
    protected function migratePlacarSchema(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2025_12_23_111918_create_permission_tables.php',
            'database/migrations/2026_08_06_090000_create_modalidades_table.php',
            'database/migrations/2026_08_06_090001_create_equipes_table.php',
            'database/migrations/2026_08_06_090002_create_times_table.php',
            'database/migrations/2026_08_06_090003_create_jogadores_table.php',
            'database/migrations/2026_08_06_090004_create_elencos_table.php',
            'database/migrations/2026_08_06_090005_create_competicoes_table.php',
            'database/migrations/2026_08_06_090006_create_jogos_table.php',
            'database/migrations/2026_08_06_090007_create_escalacoes_table.php',
            'database/migrations/2026_08_06_090008_create_jogo_eventos_table.php',
            'database/migrations/2026_08_06_114625_create_personal_access_tokens_table.php',
            'database/migrations/2026_08_06_114700_create_placar_api_clientes_table.php',
            'database/migrations/2026_08_18_100000_add_video_path_to_jogadores_table.php',
        ]]);
    }
}
