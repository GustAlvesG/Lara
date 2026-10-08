<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O "Local do atendimento" saiu do módulo: o atendimento é sempre no mesmo
 * lugar, e o campo só repetia o valor padrão. O que identifica o atendimento
 * é quem gerou o documento (`created_by_name`) e quem mandou cada e-mail.
 *
 * Os manifestos já emitidos com o local continuam como estão: são PDFs
 * guardados, não releem a coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('signature_documents', 'location')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropColumn('location');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('signature_documents', 'location')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->string('location', 120)->nullable()->after('validation_code');
            });
        }
    }
};
