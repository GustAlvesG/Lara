<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lugar marcado nos ingressos (28/10/2026): mesas, bistrôs, camarotes
 * escolhidos num mapa.
 *
 *   ticket_maps: os mapas do evento ("Mesas", "Bistrôs"), com imagem de fundo
 *     opcional, o tamanho da área de desenho e as áreas de orientação (palco,
 *     piscina, banheiros) em JSON — só desenho, não se vendem.
 *   ticket_seats: os lugares de um mapa, com rótulo, categoria (o tipo de
 *     ingresso que vende o lugar é o que aponta para o mapa e a categoria),
 *     forma e posição na área de desenho.
 *   ticket_seat_claims: a reserva do lugar por um ingresso, num dia. O índice
 *     único (lugar, day_key) é a garantia de que o lugar não é vendido duas
 *     vezes no mesmo dia; `day_key` é o dia da diária (0 no evento sem dias).
 *   tickets.ticket_seat_id: o lugar do ingresso.
 *   ticket_types.ticket_map_id / seat_category: o tipo de lugar marcado (vende
 *     os lugares do mapa, só os da categoria se preenchida).
 *
 * Idempotente, FKs com nome curto (64 do MySQL), como as outras do módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_maps')) {
            Schema::create('ticket_maps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tmap_event_fk')->cascadeOnDelete();
                $table->string('name', 100);
                // Arquivo em public/images ou link (ImageSource).
                $table->string('background')->nullable();
                // A área de desenho, em unidades: as posições dos lugares e áreas usam a mesma escala.
                $table->unsignedSmallInteger('width')->default(1000);
                $table->unsignedSmallInteger('height')->default(600);
                $table->json('areas')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ticket_seats')) {
            Schema::create('ticket_seats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_map_id')->constrained('ticket_maps', 'id', 'tseat_map_fk')->cascadeOnDelete();
                $table->string('label', 20);
                $table->string('category', 60)->nullable();
                $table->string('shape', 10)->default('circle');
                // Centro do lugar e tamanho, nas unidades do mapa.
                $table->unsignedSmallInteger('x');
                $table->unsignedSmallInteger('y');
                $table->unsignedSmallInteger('w')->default(40);
                $table->unsignedSmallInteger('h')->default(40);
                $table->unsignedTinyInteger('places')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique(['ticket_map_id', 'label'], 'tseat_map_label_uq');
            });
        }

        if (! Schema::hasColumn('ticket_types', 'ticket_map_id')) {
            Schema::table('ticket_types', function (Blueprint $table) {
                // Sem cascade: apagar mapa com tipo é recusado no código.
                $table->foreignId('ticket_map_id')->nullable()->after('ticket_event_day_id')
                    ->constrained('ticket_maps', 'id', 'tty_map_fk');
                $table->string('seat_category', 60)->nullable()->after('ticket_map_id');
            });
        }

        if (! Schema::hasColumn('tickets', 'ticket_seat_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                // Sem cascade: lugar com ingresso não é apagado (desative).
                $table->foreignId('ticket_seat_id')->nullable()->after('ticket_lot_id')
                    ->constrained('ticket_seats', 'id', 'tkt_seat_fk');
            });
        }

        if (! Schema::hasTable('ticket_seat_claims')) {
            Schema::create('ticket_seat_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_seat_id')->constrained('ticket_seats', 'id', 'tsc_seat_fk');
                $table->foreignId('ticket_id')->constrained('tickets', 'id', 'tsc_ticket_fk')->cascadeOnDelete();
                $table->unsignedBigInteger('day_key')->default(0);
                $table->timestamps();

                $table->unique(['ticket_seat_id', 'day_key'], 'tsc_seat_day_uq');
                $table->unique('ticket_id', 'tsc_ticket_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_seat_claims');

        if (Schema::hasColumn('tickets', 'ticket_seat_id')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropForeign('tkt_seat_fk');
                $table->dropColumn('ticket_seat_id');
            });
        }

        if (Schema::hasColumn('ticket_types', 'ticket_map_id')) {
            Schema::table('ticket_types', function (Blueprint $table) {
                $table->dropForeign('tty_map_fk');
                $table->dropColumn(['ticket_map_id', 'seat_category']);
            });
        }

        Schema::dropIfExists('ticket_seats');
        Schema::dropIfExists('ticket_maps');
    }
};
