<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneios, F6 e F7:
 *  - `tournaments.results_code_*`: o código de lançamento de resultados pelo
 *    site (a equipe do clube digita no site; o banco guarda só o sha256),
 *    com validade e quem gerou;
 *  - `tournament_registrations.refunded_amount`: o valor estornado DESTA
 *    inscrição (um pagamento cobre várias), para o relatório por categoria.
 *
 * Idempotente: confere cada coluna antes de criar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournaments', 'results_code_hash')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->string('results_code_hash', 64)->nullable()->after('status');
                $table->dateTime('results_code_expires_at')->nullable()->after('results_code_hash');
                $table->dateTime('results_code_created_at')->nullable()->after('results_code_expires_at');
                $table->foreignId('results_code_created_by')->nullable()->after('results_code_created_at')
                    ->constrained('users', 'id', 'tour_code_user_fk')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tournament_registrations', 'refunded_amount')) {
            Schema::table('tournament_registrations', function (Blueprint $table) {
                $table->decimal('refunded_amount', 10, 2)->default(0)->after('cancel_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tournament_registrations', 'refunded_amount')) {
            Schema::table('tournament_registrations', function (Blueprint $table) {
                $table->dropColumn('refunded_amount');
            });
        }

        if (Schema::hasColumn('tournaments', 'results_code_hash')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropForeign('tour_code_user_fk');
                $table->dropColumn(['results_code_hash', 'results_code_expires_at', 'results_code_created_at', 'results_code_created_by']);
            });
        }
    }
};
