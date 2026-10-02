<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas coisas que um contrato tem e um termo de uma pessoa só não tinha:
 *
 * **Partes.** O modelo passa a declarar quem assina ("Contratante",
 * "Contratado") e onde cada um assina — `[[assinatura:contratante]]` no texto.
 * O signatário guarda a parte dele; o rótulo vai junto, desnormalizado, porque
 * é ele que sai impresso sob o nome e no manifesto.
 *
 * **Visto.** A rubrica de cada signatário em todas as páginas. É um desenho
 * próprio, diferente da assinatura, capturado no tablet logo depois dela — e
 * guardado com os traços, pela mesma razão da assinatura: o traço vetorial é o
 * que separa uma rubrica de um toque na tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_templates', function (Blueprint $table) {
            // [{key, label}] — vazio/null: modelo sem partes, `[[assinatura]]`
            // único, como sempre foi.
            $table->json('parties')->nullable()->after('variables');
            $table->boolean('requires_initials')->default(false)->after('requires_photo');
        });

        Schema::table('signature_signers', function (Blueprint $table) {
            $table->string('party', 60)->nullable()->after('role');
            $table->string('party_label', 120)->nullable()->after('party');
        });

        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->string('initials_path', 255)->nullable()->after('signature_path');
            $table->longText('initials_strokes')->nullable()->after('initials_path');
        });
    }

    public function down(): void
    {
        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->dropColumn(['initials_path', 'initials_strokes']);
        });

        Schema::table('signature_signers', function (Blueprint $table) {
            $table->dropColumn(['party', 'party_label']);
        });

        Schema::table('signature_templates', function (Blueprint $table) {
            $table->dropColumn(['parties', 'requires_initials']);
        });
    }
};
