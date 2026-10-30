<?php

use App\Models\SignatureContract;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contratos: nome e documento das partes em texto puro, só para a busca.
 *
 * O `content` é JSON com o acento escapado ("Construção"): procurar a
 * contratada ali não acha nome acentuado. Aqui a collation do MySQL compara
 * sem acento e sem maiúscula. Quem mantém a coluna é o `saving` do model.
 * Idempotente; preenche os contratos que já existem.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_contracts')) {
            return;
        }

        if (!Schema::hasColumn('signature_contracts', 'parties_search')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->text('parties_search')->nullable()->after('content');
            });
        }

        DB::table('signature_contracts')->select(['id', 'content'])->orderBy('id')->each(function ($contrato) {
            DB::table('signature_contracts')->where('id', $contrato->id)->update([
                'parties_search' => SignatureContract::partiesSearchOf(json_decode((string) $contrato->content, true) ?: []),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_contracts', 'parties_search')) {
            Schema::table('signature_contracts', function (Blueprint $table) {
                $table->dropColumn('parties_search');
            });
        }
    }
};
