<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Banco mínimo dos ingressos, no SQLite :memory: da suíte.
 *
 * Mesmo desenho do CreatesTournamentSchema: migra o acesso de verdade
 * (usuários, setores, catálogo), as tabelas que o layout consulta em toda
 * tela, cria `members` no formato mínimo e roda por cima A MIGRATION DE
 * VERDADE do módulo — o teste também prova que ela aplica.
 */
trait CreatesTicketSchema
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    protected const TICKET_MIGRATION = '2026_10_27_100000_create_ticket_tables.php';

    /** Dias do evento (diária e passaporte) e o registro das entradas. */
    protected const TICKET_DAYS_MIGRATION = '2026_10_27_120000_add_ticket_event_days.php';

    /** Lote sem limite de ingressos (`quantity` nulo). */
    protected const TICKET_LOT_UNLIMITED_MIGRATION = '2026_10_28_100000_make_ticket_lot_quantity_nullable.php';

    /** Códigos de "meus ingressos" do não sócio. */
    protected const TICKET_GUEST_ACCESS_MIGRATION = '2026_10_28_110000_create_ticket_guest_access_codes.php';

    /** Lugar marcado: mapas, lugares e a reserva do lugar por dia. */
    protected const TICKET_MAPS_MIGRATION = '2026_10_28_120000_create_ticket_maps.php';

    /** Histórico do pedido, impressão térmica e mesa cativa. */
    protected const TICKET_LOGS_PRINT_CAPTIVE_MIGRATION = '2026_10_28_140000_add_ticket_logs_print_captive.php';

    protected function createTicketSchema(): void
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

        (require database_path('migrations/' . self::TICKET_MIGRATION))->up();
        (require database_path('migrations/' . self::TICKET_DAYS_MIGRATION))->up();
        (require database_path('migrations/' . self::TICKET_LOT_UNLIMITED_MIGRATION))->up();
        (require database_path('migrations/' . self::TICKET_GUEST_ACCESS_MIGRATION))->up();
        (require database_path('migrations/' . self::TICKET_MAPS_MIGRATION))->up();
        (require database_path('migrations/' . self::TICKET_LOGS_PRINT_CAPTIVE_MIGRATION))->up();

        $this->shareSqliteWithUserConnection();
    }
}
