<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Congela o DOCUMENTO no ato da assinatura: a redação das cláusulas e os
     * dados das partes que o texto cita.
     *
     * As cláusulas são reavaliadas pelo jurídico de tempos em tempos, e o corpo
     * do contrato é montado ao vivo a partir dos templates — sem estas colunas,
     * revisar uma cláusula reescreveria, retroativamente, o texto de todo
     * contrato já assinado, inclusive os já pagos e arquivados. É a mesma
     * garantia que a cópia da `pix_key` já dá para a chave do pagamento
     * (2026_08_05_120000), agora estendida ao resto do instrumento.
     *
     * - `contract_version` guarda QUAL redação foi firmada. O texto continua em
     *   arquivo, versionado (`services/partials/contract/v1/…`): assim o
     *   jurídico compara redações por diff, e a varredura é uma consulta por
     *   esta coluna.
     * - `signed_snapshot` guarda os DADOS que o texto cita — nome, CPF, RG,
     *   nacionalidade, estado civil, endereço e o nome da função. Todos são
     *   lidos hoje do cadastro, que muda depois da assinatura: corrigir o
     *   endereço de um freelancer reescrevia o preâmbulo dos contratos que ele
     *   já tinha firmado.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            // Indexado porque é por ele que se varre ("quais contratos estão na
            // redação antiga?"), que é a razão de a coluna existir.
            $table->unsignedTinyInteger('contract_version')->nullable()->index()->after('price');
            $table->json('signed_snapshot')->nullable()->after('contract_version');
        });

        // Tudo o que já está assinado foi firmado sob a redação 1 — é a única
        // que existiu até aqui. Contrato ainda sem assinatura fica nulo e segue
        // a redação vigente: é ela que ele vai assinar.
        DB::table('freelancer_services')
            ->whereNull('contract_version')
            ->where(function ($query) {
                $query->whereNotNull('freelancer_signed_at')
                    ->orWhereNotNull('coordinator_signed_at');
            })
            ->update(['contract_version' => 1]);

        // `signed_snapshot` fica nulo nos antigos, de propósito: o dado não
        // existe para ser inventado agora, e sem ele o documento cai no cadastro
        // do freelancer — que é exatamente o que ele citava antes desta coluna.
        // Mesma decisão tomada para a `pix_key` legada.
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['contract_version', 'signed_snapshot']);
        });
    }
};
