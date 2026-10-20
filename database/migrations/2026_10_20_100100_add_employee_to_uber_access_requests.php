<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedido de carro de aplicativo feito por funcionário: qual funcionário
 * conferiu (`employee_id`, sem chave estrangeira — o pedido é histórico e não
 * pode sumir nem travar a exclusão do cadastro) e, quando ele está bloqueado,
 * o porquê (`member_validation_detail`: "Desligado em 10/10/2026", "Férias
 * até 25/10/2026"), que a portaria lê junto do selo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('uber_access_requests', 'employee_id')) {
                $table->unsignedBigInteger('employee_id')->nullable()->after('member_validation_type');
                $table->index('employee_id');
            }

            if (!Schema::hasColumn('uber_access_requests', 'member_validation_detail')) {
                $table->string('member_validation_detail')->nullable()->after('member_validation_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('uber_access_requests', function (Blueprint $table) {
            if (Schema::hasColumn('uber_access_requests', 'employee_id')) {
                $table->dropIndex(['employee_id']);
                $table->dropColumn('employee_id');
            }

            if (Schema::hasColumn('uber_access_requests', 'member_validation_detail')) {
                $table->dropColumn('member_validation_detail');
            }
        });
    }
};
