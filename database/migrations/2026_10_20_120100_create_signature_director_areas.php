<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assinatura Diretoria: as áreas da diretoria do clube, quem responde por
 * cada uma (titular e substitutos) e o diretor como signatário.
 *
 * - `signature_director_areas`: "Diretoria Financeira", "Diretoria Social".
 * - `signature_director_area_holders`: o mandato — um usuário do setor
 *   Diretoria numa área, como titular ou substituto. Não se apaga: encerrar
 *   um mandato grava `ended_at`, e o histórico diz quem respondia pela área em
 *   cada época. Vigente = `ended_at` nulo.
 * - `signature_signers`: o signatário que é diretor aponta para o usuário
 *   (`user_id`) e guarda o RETRATO da área e do papel na criação do
 *   documento — o nome da área pode mudar depois, e o documento não.
 *
 * - `signature_requests.identity_method`: como a identidade foi conferida
 *   quando NÃO foi pela regra do modelo — `matricula_pin` na entrada do
 *   diretor no tablet. É o que o manifesto imprime no lugar da conferência do
 *   modelo. Nulo = a do modelo, como sempre foi.
 *
 * Sem foreign key para `users`, como no resto do módulo: o model User fixa a
 * conexão `mysql` (ver CreatesSignatureSchema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signature_director_areas')) {
            Schema::create('signature_director_areas', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120)->unique();
                $table->string('description', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        /*
         | Índices e chave estrangeira em passos SEPARADOS e com nome curto:
         | o nome automático da FK (`signature_director_area_holders_…_foreign`)
         | passa dos 64 caracteres do MySQL. Na primeira versão desta migration
         | a homologação parou exatamente aí — DDL do MySQL não volta atrás, e
         | a tabela ficou criada sem índice nem FK. Cada passo abaixo confere
         | antes de agir, para completar um banco que ficou pela metade e
         | também rodar num banco limpo. (O SQLite dos testes não acusa nome
         | longo.)
         */
        if (!Schema::hasTable('signature_director_area_holders')) {
            Schema::create('signature_director_area_holders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('signature_director_area_id');
                $table->unsignedBigInteger('user_id');
                // Retrato do nome: a tela de histórico não depende de `users`.
                $table->string('user_name', 150);
                $table->string('role', 20);
                $table->date('starts_on');
                $table->timestamp('ended_at')->nullable();
                $table->unsignedBigInteger('set_by')->nullable();
                $table->string('set_by_name', 150)->nullable();
                $table->unsignedBigInteger('ended_by')->nullable();
                $table->string('ended_by_name', 150)->nullable();
                $table->timestamps();
            });
        }

        $indices = array_column(Schema::getIndexes('signature_director_area_holders'), 'name');

        Schema::table('signature_director_area_holders', function (Blueprint $table) use ($indices) {
            if (!in_array('sig_dir_holders_area_end_idx', $indices, true)) {
                $table->index(['signature_director_area_id', 'ended_at'], 'sig_dir_holders_area_end_idx');
            }

            if (!in_array('sig_dir_holders_user_idx', $indices, true)) {
                $table->index('user_id', 'sig_dir_holders_user_idx');
            }
        });

        $fks = array_column(Schema::getForeignKeys('signature_director_area_holders'), 'name');

        if (!in_array('sig_dir_holders_area_fk', $fks, true)) {
            Schema::table('signature_director_area_holders', function (Blueprint $table) {
                $table->foreign('signature_director_area_id', 'sig_dir_holders_area_fk')
                    ->references('id')
                    ->on('signature_director_areas')
                    ->restrictOnDelete();
            });
        }

        Schema::table('signature_signers', function (Blueprint $table) {
            if (!Schema::hasColumn('signature_signers', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('member_id')->index();
            }

            if (!Schema::hasColumn('signature_signers', 'director_area_id')) {
                $table->unsignedBigInteger('director_area_id')->nullable()->after('user_id')->index();
                $table->string('director_area_name', 120)->nullable()->after('director_area_id');
                $table->string('director_role', 20)->nullable()->after('director_area_name');
                $table->timestamp('director_notified_at')->nullable()->after('director_role');
            }
        });

        if (!Schema::hasColumn('signature_requests', 'identity_method')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->string('identity_method', 30)->nullable()->after('identity_confirmed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('signature_requests', 'identity_method')) {
            Schema::table('signature_requests', function (Blueprint $table) {
                $table->dropColumn('identity_method');
            });
        }

        if (Schema::hasColumn('signature_signers', 'director_area_id')) {
            Schema::table('signature_signers', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['director_area_id']);
                $table->dropColumn(['director_notified_at', 'director_role', 'director_area_name', 'director_area_id', 'user_id']);
            });
        }

        // A FK cai junto com a tabela.
        Schema::dropIfExists('signature_director_area_holders');
        Schema::dropIfExists('signature_director_areas');
    }
};
