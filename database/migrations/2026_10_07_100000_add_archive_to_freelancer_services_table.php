<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cópia do contrato assinado no servidor de arquivos (FTP).
     *
     * O contrato do freelancer não tem PDF guardado no sistema: ele é montado
     * a cada exibição, a partir da redação e da qualificação congeladas na
     * assinatura. O PDF nasce na hora de arquivar, e estas colunas dizem que
     * ele foi, para onde, e qual era — o hash permite conferir, depois, se o
     * arquivo da pasta ainda é o que o sistema enviou.
     *
     * `archived_at` nulo é a fila do comando `freelancers:archive`.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->string('archive_path', 500)->nullable();
            $table->string('archive_sha256', 64)->nullable();
            $table->dateTime('archived_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archive_path', 'archive_sha256', 'archived_at']);
        });
    }
};
