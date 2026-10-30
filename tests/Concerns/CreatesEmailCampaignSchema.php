<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;

/**
 * Banco mínimo do Disparo de E-mails, no SQLite :memory: da suíte: o acesso
 * de verdade (usuários, setores, catálogo), as tabelas que o layout consulta
 * em toda tela e A MIGRATION DE VERDADE do módulo — o teste também prova que
 * ela aplica.
 */
trait CreatesEmailCampaignSchema
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected function createEmailCampaignSchema(): void
    {
        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            // O layout consulta notificações e avisos em toda tela.
            'database/migrations/2026_06_10_000001_create_notifications_table.php',
            'database/migrations/2026_06_10_000002_create_avisos_table.php',
            'database/migrations/2026_06_10_000003_avisos_privacy_and_lembretes.php',
            'database/migrations/2026_06_10_000004_create_aviso_views_table.php',
            'database/migrations/2026_06_10_000006_create_tags_tables.php',
            'database/migrations/2026_06_12_000002_create_aviso_user_table.php',
            'database/migrations/2026_07_31_120000_add_mandatory_reading_to_avisos.php',
            'database/migrations/2026_10_30_110000_create_email_campaign_tables.php',
            'database/migrations/2026_10_30_120000_add_visual_editor_to_email_campaigns.php',
        ]]);

        $this->shareSqliteWithUserConnection();
    }
}
