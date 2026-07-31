<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * O código de liberação deixa de ser endereçado a UM coordenador: agora um
     * único código vai para TODOS os coordenadores do Comercial, e quem estiver
     * disponível dita. Consequências no registro:
     *
     *  - `coordinator_id` fica nulo — o código é do contrato, não de uma pessoa;
     *  - `sent_to` passa a guardar a lista de destinatários, que não cabe em 255.
     *
     * Sem dono, também não há a quem atribuir a liberação: quando o contrato é
     * liberado por código, `weekly_limit_authorized_by` fica nulo (só a data é
     * gravada). Dizer que foi um coordenador específico seria inventar.
     */
    public function up(): void
    {
        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
        });

        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable()->change();
            $table->text('sent_to')->change();
        });

        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->foreign('coordinator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Códigos sem coordenador não cabem no formato antigo; como são de uso
     * imediato e curta validade, são descartados na volta.
     */
    public function down(): void
    {
        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->dropForeign(['coordinator_id']);
        });

        \Illuminate\Support\Facades\DB::table('freelancer_weekly_limit_codes')
            ->whereNull('coordinator_id')
            ->delete();

        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->foreignId('coordinator_id')->nullable(false)->change();
            $table->string('sent_to')->change();
        });

        Schema::table('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->foreign('coordinator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
