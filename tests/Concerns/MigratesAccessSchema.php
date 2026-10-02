<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;

/**
 * Migra só o que o acesso precisa — usuários, status, setores, as tabelas do
 * Spatie e as da reforma do acesso — e nunca a suíte de migrations inteira,
 * que quebra no SQLite por um bug antigo e fora de escopo (`members.title`
 * criada duas vezes — ver MigratesPlacarSchema).
 *
 * Inclui a `sync_access_catalog`, então o banco já nasce com o catálogo, os
 * setores e a matriz inicial, exatamente como num deploy.
 */
trait MigratesAccessSchema
{
    protected function migrateAccessSchema(): void
    {
        Artisan::call('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2024_05_20_140004_add_column_role.php',
            'database/migrations/2025_08_28_091904_create_status_table.php',
            'database/migrations/2025_12_23_111918_create_permission_tables.php',
            'database/migrations/2025_12_24_073138_add_coluns_on_user.php',
            'database/migrations/2025_12_24_091203_description_permission.php',
            'database/migrations/2025_12_30_132516_deleted_at_user.php',
            'database/migrations/2026_06_05_102714_create_sectors_table.php',
            'database/migrations/2026_06_05_102715_create_user_sector_table.php',
            'database/migrations/2026_07_23_100001_add_pin_to_users_table.php',
            'database/migrations/2026_08_13_100000_add_approval_password_and_phone_to_users_table.php',
            'database/migrations/2026_09_30_100000_add_full_access_to_sectors.php',
            'database/migrations/2026_09_30_100100_create_sector_permission_table.php',
            'database/migrations/2026_09_30_100200_create_access_audit_logs_table.php',
            'database/migrations/2026_09_30_100300_sync_access_catalog.php',
        ]]);
    }
}
