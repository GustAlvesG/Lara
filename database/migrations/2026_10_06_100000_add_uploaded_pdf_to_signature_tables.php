<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documento PRONTO, enviado em PDF: entra na íntegra — texto, imagens,
 * diagramação — e o sistema só carimba por cima a assinatura e o visto.
 *
 *  - `source_path` / `source_sha256` (documento): o PDF como o atendente o
 *    enviou, e o hash dele. O hash vai ao manifesto: é o que permite conferir,
 *    depois, que o que foi assinado é o arquivo que foi enviado.
 *  - `signature_position` (signatário): onde, no PDF, a assinatura daquela
 *    pessoa é carimbada — página e ponto, como fração da página. Nulo = ela
 *    assina na folha de assinaturas que o sistema acrescenta ao fim.
 *  - `single_use` (modelo): um documento pronto não tem modelo, mas as regras
 *    da assinatura (identidade, foto, visto) moram no modelo. Cada envio cria
 *    o seu, de uso único, que não aparece na lista de modelos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_templates', function (Blueprint $table) {
            $table->boolean('single_use')->default(false)->after('active');
        });

        Schema::table('signature_documents', function (Blueprint $table) {
            $table->string('source_path', 255)->nullable()->after('body_snapshot');
            $table->char('source_sha256', 64)->nullable()->after('source_path');
        });

        Schema::table('signature_signers', function (Blueprint $table) {
            $table->json('signature_position')->nullable()->after('party_label');
        });
    }

    public function down(): void
    {
        Schema::table('signature_signers', function (Blueprint $table) {
            $table->dropColumn('signature_position');
        });

        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn(['source_path', 'source_sha256']);
        });

        Schema::table('signature_templates', function (Blueprint $table) {
            $table->dropColumn('single_use');
        });
    }
};
