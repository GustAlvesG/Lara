<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ingressos de evento de vários dias (27/10/2026): diária ou passaporte.
 *
 *   ticket_event_days: os dias do evento, cada um com o próprio horário (o dia
 *     pode passar da meia-noite). Evento sem dias = sessão única, como antes.
 *   ticket_types.ticket_event_day_id: o dia da diária; nulo num evento com dias
 *     = passaporte (vale todos os dias); nulo num evento sem dias = ingresso
 *     do evento.
 *   ticket_checkins: cada entrada registrada. `day_key` é o id do dia (0 no
 *     evento sem dias) e o índice único (ticket, day_key) garante uma entrada
 *     por ingresso por dia — o passaporte entra uma vez em cada dia.
 *
 * Idempotente, como as outras do módulo. As entradas já gravadas no ingresso
 * (`checked_in_at`) viram linhas de ticket_checkins.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_event_days')) {
            Schema::create('ticket_event_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_event_id')->constrained('ticket_events', 'id', 'tday_event_fk')->cascadeOnDelete();
                // Opcional ("Sábado", "Dia 1"); vazio = a data.
                $table->string('name', 100)->nullable();
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->timestamps();

                $table->index(['ticket_event_id', 'starts_at'], 'tday_event_start_idx');
            });
        }

        if (! Schema::hasColumn('ticket_types', 'ticket_event_day_id')) {
            Schema::table('ticket_types', function (Blueprint $table) {
                // Sem cascade: apagar dia com tipo é recusado no código.
                $table->foreignId('ticket_event_day_id')->nullable()->after('ticket_event_id')
                    ->constrained('ticket_event_days', 'id', 'tty_day_fk');
            });
        }

        if (! Schema::hasTable('ticket_checkins')) {
            Schema::create('ticket_checkins', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('tickets', 'id', 'tci_ticket_fk')->cascadeOnDelete();
                $table->foreignId('ticket_event_day_id')->nullable()->constrained('ticket_event_days', 'id', 'tci_day_fk')->nullOnDelete();
                $table->unsignedBigInteger('day_key')->default(0);
                $table->dateTime('checked_in_at');
                $table->foreignId('checked_in_by')->nullable()->constrained('users', 'id', 'tci_checker_fk');
                $table->string('source', 20);
                $table->timestamps();

                $table->unique(['ticket_id', 'day_key'], 'tci_ticket_day_uq');
                $table->index(['day_key', 'checked_in_at'], 'tci_day_idx');
            });

            DB::table('tickets')->whereNotNull('checked_in_at')->orderBy('id')->each(function ($ticket) {
                DB::table('ticket_checkins')->insert([
                    'ticket_id' => $ticket->id,
                    'day_key' => 0,
                    'checked_in_at' => $ticket->checked_in_at,
                    'checked_in_by' => $ticket->checked_in_by,
                    'source' => $ticket->checkin_source ?? 'lara',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_checkins');

        if (Schema::hasColumn('ticket_types', 'ticket_event_day_id')) {
            Schema::table('ticket_types', function (Blueprint $table) {
                $table->dropForeign('tty_day_fk');
                $table->dropColumn('ticket_event_day_id');
            });
        }

        Schema::dropIfExists('ticket_event_days');
    }
};
