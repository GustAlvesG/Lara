<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assinatura da diretoria no documento (redação 2 do contrato).
     *
     * Na redação 2 quem assina pelo CONTRATANTE é o diretor: a coordenação só
     * valida o contrato pela web, e a imagem da assinatura do diretor entra no
     * documento quando ele aprova o lote.
     *
     * - `freelancer_service_batches.freelancer_director_id` — o cadastro para o
     *   qual o e-mail com os códigos foi enviado. A assinatura aplicada é a de
     *   QUEM RECEBEU o e-mail, e não a do cadastro vigente no dia em que a
     *   gerência digita o código: se o cadastro mudar no meio, o documento
     *   continua levando a assinatura de quem de fato decidiu.
     * - `freelancer_services.freelancer_director_id` / `director_signed_at` —
     *   a assinatura aplicada ao documento. Colunas próprias, e não
     *   `director_approved_at`, porque o contrato base que ganhou aditivo
     *   também é assinado (pelo aditivo que o substituiu) sem ter sido
     *   aprovado como pagamento — marcá-lo como aprovado mexeria nas travas de
     *   pagamento e de aditivo dele.
     *
     * Sem foreign key para `freelancer_directors`: os cadastros nunca são
     * apagados (não existe rota para isso), e a FK complicaria a montagem do
     * schema dos testes no SQLite sem proteger nada a mais.
     */
    public function up(): void
    {
        Schema::table('freelancer_service_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('freelancer_director_id')->nullable()->after('director_email');
        });

        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->unsignedBigInteger('freelancer_director_id')->nullable()->after('director_rejected_at');
            $table->dateTime('director_signed_at')->nullable()->after('freelancer_director_id');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['freelancer_director_id', 'director_signed_at']);
        });

        Schema::table('freelancer_service_batches', function (Blueprint $table) {
            $table->dropColumn('freelancer_director_id');
        });
    }
};
