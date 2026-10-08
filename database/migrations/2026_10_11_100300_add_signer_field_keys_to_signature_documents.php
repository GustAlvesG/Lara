<?php

use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Perguntar a quem assina" sai do modelo e vai para o documento: o modelo
 * declara os campos, e o atendente decide, no preenchimento, quais vão ao
 * tablet. Assim o mesmo modelo pode ir ao balcão (com perguntas) ou ao gov.br
 * (sem elas).
 *
 * `signer_field_keys` é a lista das chaves marcadas. Os documentos que já
 * existem herdam o que o modelo deles marcava — um documento congelado ontem
 * tem de continuar perguntando o que perguntava. O `ask_signer` gravado nos
 * modelos fica no JSON, mas não é mais lido.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('signature_documents', 'signer_field_keys')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->json('signer_field_keys')->nullable()->after('data');
            });
        }

        $perguntasDoModelo = [];

        foreach (DB::table('signature_templates')->get(['id', 'variables']) as $modelo) {
            $chaves = [];

            foreach ((array) json_decode((string) $modelo->variables, true) as $v) {
                $tipo = $v['type'] ?? SignatureFieldTypes::TEXT;

                if (!empty($v['ask_signer']) && !SignatureFieldTypes::isAutomatic($tipo) && ($v['key'] ?? '') !== '') {
                    $chaves[] = (string) $v['key'];
                }
            }

            if ($chaves !== []) {
                $perguntasDoModelo[$modelo->id] = $chaves;
            }
        }

        foreach ($perguntasDoModelo as $modeloId => $chaves) {
            DB::table('signature_documents')
                ->where('signature_template_id', $modeloId)
                ->whereNull('signer_field_keys')
                ->update(['signer_field_keys' => json_encode($chaves)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_documents', 'signer_field_keys')) {
            Schema::table('signature_documents', function (Blueprint $table) {
                $table->dropColumn('signer_field_keys');
            });
        }
    }
};
