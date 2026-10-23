<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Banco mínimo dos torneios, no SQLite :memory: da suíte.
 *
 * Sem RefreshDatabase (a cadeia inteira de migrations não sobe em SQLite):
 * migra o acesso de verdade (usuários, setores, catálogo), as tabelas que o
 * layout consulta em toda tela, cria `place_groups` e `members` no formato
 * mínimo e roda por cima A MIGRATION DE VERDADE do módulo — o teste também
 * prova que ela aplica.
 *
 * A conexão do User (presa a `mysql`) passa a usar o mesmo banco em memória.
 */
trait CreatesTournamentSchema
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected const TOURNAMENT_MIGRATION = '2026_10_22_110000_create_tournament_tables.php';
    protected const BRACKET_MIGRATION = '2026_10_23_100000_add_bracket_fields_to_tournament_tables.php';
    protected const RESULTS_MIGRATION = '2026_10_23_110000_add_results_and_report_fields_to_tournament_tables.php';

    protected function createTournamentSchema(): void
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
        ]]);

        Schema::create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('cpf')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('title')->nullable();
            $table->timestamps();
            // Member usa SoftDeletes (Contas do site).
            $table->softDeletes();
        });

        (require database_path('migrations/' . self::TOURNAMENT_MIGRATION))->up();
        (require database_path('migrations/' . self::BRACKET_MIGRATION))->up();
        (require database_path('migrations/' . self::RESULTS_MIGRATION))->up();

        $this->shareSqliteWithUserConnection();
    }
}
