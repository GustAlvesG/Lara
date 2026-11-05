<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos (05/11/2026, pedidos da apresentação do módulo):
 *
 *  - Prestadores: o cadastro de contratadas (`signature_contract_parties`)
 *    ganhou tela própria. Prestador não se exclui, só se inativa (`active`):
 *    o inativo some da busca do editor, mas os contratos que o usam seguem
 *    intactos (os dados da parte ficam no próprio contrato).
 *  - Salvar automático do editor: `content_revision` sobe a cada gravação
 *    do texto e `content_saved_by_name`/`content_saved_at` dizem quem gravou
 *    por último — é o que permite avisar "alguém salvou antes de você" em
 *    vez de gravar por cima.
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('signature_contract_parties') && !Schema::hasColumn('signature_contract_parties', 'active')) {
            Schema::table('signature_contract_parties', function (Blueprint $table) {
                $table->boolean('active')->default(true)->after('data');
                $table->string('created_by_name', 120)->nullable()->after('updated_by_name');
            });
        }

        if (Schema::hasTable('signature_contracts') && !Schema::hasColumn('signature_contracts', 'content_revision')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->unsignedInteger('content_revision')->default(0)->after('content');
                $table->string('content_saved_by_name', 120)->nullable()->after('content_revision');
                $table->timestamp('content_saved_at')->nullable()->after('content_saved_by_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contracts', 'content_revision')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->dropColumn(['content_revision', 'content_saved_by_name', 'content_saved_at']);
            });
        }

        if (Schema::hasColumn('signature_contract_parties', 'active')) {
            Schema::table('signature_contract_parties', function (Blueprint $table) {
                $table->dropColumn(['active', 'created_by_name']);
            });
        }
    }
};
