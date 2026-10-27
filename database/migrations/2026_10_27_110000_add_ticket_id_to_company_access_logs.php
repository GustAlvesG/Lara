<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ingressos no Monitor de Acesso (27/10/2026): a entrada pelo ingresso cai no
 * mesmo histórico dos terceirizados, com o ingresso que a liberou (ou foi
 * negado). Idempotente; FK com nome curto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('company_access_logs') && ! Schema::hasColumn('company_access_logs', 'ticket_id')) {
            Schema::table('company_access_logs', function (Blueprint $table) {
                $table->foreignId('ticket_id')->nullable()->after('one_off_access_id')
                    ->constrained('tickets', 'id', 'cal_ticket_fk')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('company_access_logs', 'ticket_id')) {
            Schema::table('company_access_logs', function (Blueprint $table) {
                $table->dropForeign('cal_ticket_fk');
                $table->dropColumn('ticket_id');
            });
        }
    }
};
