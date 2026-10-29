<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo vinculado: "quem assina X assina também Y" (sentido único).
 *
 *  - `signature_templates.linked_templates`: os modelos que X exige, com a
 *    correspondência de campos e partes feita no ato de vincular. Versionado
 *    com X, como os anexos.
 *  - `signature_documents.linked_templates`: raiz => versão de cada modelo
 *    exigido que entrou no documento, gravado na criação. Revisar Y depois não
 *    muda o texto de um documento já criado.
 *
 * Ver App\Services\Signature\LinkedTemplateComposer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_templates', 'linked_templates')) {
            Schema::table('signature_templates', function (Blueprint $table) {
                $table->json('linked_templates')->nullable()->after('review_items');
            });
        }

        if (!Schema::hasColumn('signature_documents', 'linked_templates')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->json('linked_templates')->nullable()->after('template_version');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_documents', 'linked_templates')) {
            Schema::table('signature_documents', fn(Blueprint $table) => $table->dropColumn('linked_templates'));
        }

        if (Schema::hasColumn('signature_templates', 'linked_templates')) {
            Schema::table('signature_templates', fn(Blueprint $table) => $table->dropColumn('linked_templates'));
        }
    }
};
