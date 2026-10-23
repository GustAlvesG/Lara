<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Banco mínimo das reservas de espaço social, no SQLite :memory: da suíte.
 *
 * Sem RefreshDatabase (a cadeia inteira de migrations não sobe em SQLite —
 * ver ScheduleInProgressSlotTest): as tabelas de antes do módulo são criadas
 * aqui no formato que tinham, e por cima delas rodam AS MIGRATIONS DE VERDADE
 * do módulo (14/10/2026). Assim o teste também prova que elas aplicam.
 *
 * `members` e `users` estão presos à conexão `mysql`; SharesSqliteWithUserConnection
 * aponta essa conexão para o mesmo banco em memória (as consultas cruzam:
 * reserva × sócio).
 */
trait CreatesSocialBookingSchema
{
    use SharesSqliteWithUserConnection;

    private const SOCIAL_MIGRATIONS = [
        '2026_10_14_100000_add_social_fields_to_place_groups_and_places.php',
        '2026_10_14_100100_create_place_conflicts_table.php',
        '2026_10_14_100200_create_social_beverages_table.php',
        '2026_10_14_100300_create_social_bookings_tables.php',
        '2026_10_16_100000_add_furniture_duration_and_contact_to_social_bookings.php',
        // Baixa manual de pagamento (quadras e espaços sociais).
        '2026_10_19_100000_add_manual_fields_to_schedule_payments.php',
    ];

    /**
     * `$withUsers = false` quando o teste já migrou o acesso de verdade
     * (MigratesAccessSchema), que cria `users` completo.
     */
    protected function createSocialBookingSchema(bool $withUsers = true): void
    {
        Schema::create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('image_vertical')->nullable();
            $table->string('image_horizontal')->nullable();
            $table->boolean('status')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('duration')->nullable();
            $table->time('start_time_sales')->nullable();
            $table->time('end_time_sales')->nullable();
            $table->integer('minimum_antecedence')->default(0);
            $table->integer('maximum_antecedence')->default(0);
            $table->integer('daily_limit')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('contactor_id')->nullable();
            $table->boolean('self_service_lighting')->default(false);
            $table->unsignedBigInteger('place_group_id');
            $table->string('image')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedBigInteger('status_id')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('weekdays', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('week_days_place_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekday_id');
            $table->unsignedBigInteger('place_group_id');
            $table->timestamps();
        });

        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('place_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('schedule_rule_id');
            $table->timestamps();
        });

        Schema::create('week_days_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekday_id');
            $table->unsignedBigInteger('schedule_rule_id');
            $table->timestamps();
        });

        Schema::create('schedule_payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_method')->nullable();
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_integration_id')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->unsignedBigInteger('refunded_by')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->dateTime('start_schedule');
            $table->dateTime('end_schedule');
            $table->unsignedBigInteger('status_id')->default(1);
            $table->decimal('price', 8, 2)->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('schedule_payment_id')->nullable();
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->unsignedBigInteger('updated_by_user')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('one_off_accesses', function (Blueprint $table) {
            $table->id();
            $table->string('cpf', 11)->index();
            $table->string('name');
            $table->text('reason');
            $table->string('image')->nullable();
            $table->date('access_date')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->unsignedBigInteger('canceled_by_user')->nullable();
            $table->unsignedBigInteger('created_by_user')->nullable();
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

        if ($withUsers) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        foreach (self::SOCIAL_MIGRATIONS as $file) {
            (require database_path('migrations/' . $file))->up();
        }

        $this->shareSqliteWithUserConnection();

        DB::table('weekdays')->insert(array_map(fn ($id) => ['id' => $id, 'name' => "d{$id}"], range(1, 7)));
    }
}
