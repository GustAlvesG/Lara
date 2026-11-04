<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visto da Gerência na aprovação do contrato (04/11/2026): o gerente desenha
 * a rubrica ao aprovar, e ela sai em todas as páginas do documento de
 * assinatura, na faixa do visto.
 *
 *  - `signature_contract_versions.approval_initials_path`: a rubrica, na versão
 *    aprovada (é a decisão que ela acompanha);
 *  - `signature_documents.approval_initials_path` / `_label`: a cópia presa ao
 *    documento gerado — o PDF final é refeito meses depois com ela.
 *
 * Nulo = sem visto da Gerência (contratos aprovados antes, e todo documento
 * que não veio de contrato). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contract_versions') && !Schema::hasColumn('signature_contract_versions', 'approval_initials_path')) {
            Schema::table('signature_contract_versions', function (Blueprint $table) {
                $table->string('approval_initials_path')->nullable();
            });
        }

        if (Schema::hasTable('signature_documents') && !Schema::hasColumn('signature_documents', 'approval_initials_path')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->string('approval_initials_path')->nullable();
                $table->string('approval_initials_label', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_documents', 'approval_initials_path')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropColumn(['approval_initials_path', 'approval_initials_label']);
            });
        }

        if (Schema::hasColumn('signature_contract_versions', 'approval_initials_path')) {
            Schema::table('signature_contract_versions', function (Blueprint $table) {
                $table->dropColumn('approval_initials_path');
            });
        }
    }
};
