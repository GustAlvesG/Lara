<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onde a cópia do documento assinado foi arquivada no servidor de arquivos
 * (FTP), e quando.
 *
 * O arquivo de verdade continua sendo o `final_path`, no disco privado do
 * módulo: é dele o `final_sha256`, e é dele que o painel baixa. O FTP é a
 * cópia organizada em pastas para quem procura um documento sem passar pelo
 * sistema. `archived_at` nulo num documento finalizado = cópia pendente, que
 * o comando `signature:archive` reenvia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->string('archive_path', 500)->nullable()->after('finalized_at');
            $table->timestamp('archived_at')->nullable()->after('archive_path');
        });
    }

    public function down(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn(['archive_path', 'archived_at']);
        });
    }
};
