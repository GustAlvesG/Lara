<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingressos (28/10/2026): rastreio, impressão térmica e mesa cativa.
 *
 *   ticket_order_logs: o histórico do pedido — quem (usuário do Lara; nulo =
 *     site ou rotina), quando e o quê (emissão, baixa, troca de titular ou de
 *     lugar, cancelamento, estorno, impressão…), com os detalhes em JSON.
 *   tickets.print_count / printed_at: quantas vezes o ingresso físico saiu na
 *     impressora térmica (a partir da segunda, "2ª via").
 *   ticket_seats.captive_title / captive_name: a mesa cativa — só quem é
 *     daquela matrícula (título) compra o lugar, até a liberação.
 *   ticket_events.captive_release_days: quantos dias antes do início do evento
 *     as mesas cativas não compradas viram lugar comum (nulo = não liberam
 *     sozinhas).
 *   tickets.holder_cpf / ticket_orders.buyer_cpf passam a aceitar nulo: a
 *     cortesia emitida pelo Lara pode sair sem CPF (e em quantidade, "Emitir
 *     30 cortesias"). Todo o resto continua exigindo o CPF no código. O down
 *     não volta a exigir (haveria linhas nulas).
 *
 * Idempotente, FKs com nome curto (64 do MySQL), como as outras do módulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_order_logs')) {
            Schema::create('ticket_order_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_order_id')->constrained('ticket_orders', 'id', 'tolog_order_fk')->cascadeOnDelete();
                $table->foreignId('ticket_id')->nullable()->constrained('tickets', 'id', 'tolog_ticket_fk')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'tolog_user_fk');
                $table->string('action', 30);
                $table->json('details')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['user_id', 'created_at'], 'tolog_user_at_ix');
            });
        }

        if (! Schema::hasColumn('tickets', 'print_count')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unsignedSmallInteger('print_count')->default(0)->after('checkin_source');
                $table->timestamp('printed_at')->nullable()->after('print_count');
            });
        }

        if (! Schema::hasColumn('ticket_seats', 'captive_title')) {
            Schema::table('ticket_seats', function (Blueprint $table) {
                $table->string('captive_title', 20)->nullable()->after('active');
                $table->string('captive_name')->nullable()->after('captive_title');
            });
        }

        // Cortesia sem CPF. change() é idempotente: rodar de novo não estraga.
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('holder_cpf', 11)->nullable()->change();
        });
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->string('buyer_cpf', 11)->nullable()->change();
        });

        if (! Schema::hasColumn('ticket_events', 'captive_release_days')) {
            Schema::table('ticket_events', function (Blueprint $table) {
                $table->unsignedSmallInteger('captive_release_days')->nullable()->after('max_per_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_events', 'captive_release_days')) {
            Schema::table('ticket_events', fn (Blueprint $table) => $table->dropColumn('captive_release_days'));
        }
        if (Schema::hasColumn('ticket_seats', 'captive_title')) {
            Schema::table('ticket_seats', fn (Blueprint $table) => $table->dropColumn(['captive_title', 'captive_name']));
        }
        if (Schema::hasColumn('tickets', 'print_count')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn(['print_count', 'printed_at']));
        }
        Schema::dropIfExists('ticket_order_logs');
    }
};
