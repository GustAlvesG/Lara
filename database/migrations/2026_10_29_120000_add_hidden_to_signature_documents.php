<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Arquivar" da lista de documentos: tira o documento da lista padrão, e só.
 *
 * Não confundir com `archived_at`/`archive_path`, que são a cópia no FTP
 * (`signature:archive`) — por isso o nome `hidden_*`. Arquivar não muda o
 * status, não cancela nada, não entra na trilha de auditoria e não impede a
 * assinatura: o documento segue abrindo pelo endereço, pelo `/validar` e pelo
 * filtro "Arquivados" da lista. `hidden_by_name` é retrato, como o
 * `created_by_name` (sem FK para `users`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_documents', 'hidden_at')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable()->after('initials_waived_at');
                $table->unsignedBigInteger('hidden_by')->nullable()->after('hidden_at');
                $table->string('hidden_by_name')->nullable()->after('hidden_by');
                $table->index('hidden_at', 'sig_documents_hidden_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_documents', 'hidden_at')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropIndex('sig_documents_hidden_at_idx');
                $table->dropColumn(['hidden_at', 'hidden_by', 'hidden_by_name']);
            });
        }
    }
};
