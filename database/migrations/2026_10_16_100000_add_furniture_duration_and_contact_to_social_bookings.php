<?php

use App\Support\BusinessDays;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Espaços sociais, segunda leva (16/10/2026):
 *
 * - **Mesas e cadeiras.** Cada espaço tem uma quantidade base; o sócio pode
 *   somar mesas, cadeiras ou jogos completos. Esses adicionais entram no mesmo
 *   catálogo das bebidas, com `category = mobiliario` e quantas mesas/cadeiras
 *   cada unidade traz (jogo completo = 1 mesa + 4 cadeiras), mais um máximo
 *   por reserva. A reserva guarda a cópia da base e de cada item.
 * - **Duração do evento.** Por espaço: quantas horas dura a festa e, se
 *   quiser, a faixa em que pode começar. O sócio indica o início; o fim é
 *   início + duração. A ocupação continua sendo o dia inteiro.
 * - **Prazo de contato.** A equipe tem N dias úteis (padrão 2) depois da
 *   pré-reserva para falar com o sócio; o Lara avisa no sino e marca o atraso.
 *   As pré-reservas que já existem ganham o prazo contado da criação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_beverages', function (Blueprint $table) {
            $table->string('category', 20)->default('bebida')->after('name')->index();
            $table->unsignedSmallInteger('tables_per_unit')->default(0)->after('price');
            $table->unsignedSmallInteger('chairs_per_unit')->default(0)->after('tables_per_unit');
            $table->unsignedInteger('max_quantity')->nullable()->after('chairs_per_unit');
        });

        Schema::table('social_booking_items', function (Blueprint $table) {
            $table->string('category', 20)->default('bebida')->after('social_beverage_id');
            $table->unsignedSmallInteger('tables_per_unit')->default(0)->after('subtotal');
            $table->unsignedSmallInteger('chairs_per_unit')->default(0)->after('tables_per_unit');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->unsignedInteger('base_tables')->nullable()->after('extra_guest_price');
            $table->unsignedInteger('base_chairs')->nullable()->after('base_tables');
            $table->unsignedTinyInteger('event_duration_hours')->nullable()->after('base_chairs');
            $table->time('event_earliest_start')->nullable()->after('event_duration_hours');
            $table->time('event_latest_start')->nullable()->after('event_earliest_start');
        });

        Schema::table('social_bookings', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('event_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedTinyInteger('duration_hours')->nullable()->after('end_time');
            $table->unsignedInteger('base_tables')->nullable()->after('expected_guests');
            $table->unsignedInteger('base_chairs')->nullable()->after('base_tables');

            $table->dateTime('contact_due_at')->nullable()->index()->after('stage');
            $table->timestamp('contacted_at')->nullable()->after('contact_due_at');
            $table->unsignedBigInteger('contacted_by')->nullable()->after('contacted_at');
            $table->text('contact_notes')->nullable()->after('contacted_by');
            $table->timestamp('contact_overdue_notified_at')->nullable()->after('contact_notes');
        });

        // Pré-reservas de antes desta migration: prazo a partir da criação.
        $days = (int) config('social_bookings.contact_business_days', 2);
        DB::table('social_bookings')->whereNull('contact_due_at')->orderBy('id')->get(['id', 'created_at'])
            ->each(fn ($row) => DB::table('social_bookings')->where('id', $row->id)->update([
                'contact_due_at' => BusinessDays::deadline(Carbon::parse($row->created_at), $days),
            ]));
    }

    public function down(): void
    {
        Schema::table('social_bookings', function (Blueprint $table) {
            $table->dropIndex(['contact_due_at']);
            $table->dropColumn([
                'start_time', 'end_time', 'duration_hours', 'base_tables', 'base_chairs',
                'contact_due_at', 'contacted_at', 'contacted_by', 'contact_notes', 'contact_overdue_notified_at',
            ]);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['base_tables', 'base_chairs', 'event_duration_hours', 'event_earliest_start', 'event_latest_start']);
        });

        Schema::table('social_booking_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'tables_per_unit', 'chairs_per_unit']);
        });

        Schema::table('social_beverages', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'tables_per_unit', 'chairs_per_unit', 'max_quantity']);
        });
    }
};
