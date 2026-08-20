<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jogador passa a pertencer a UMA equipe e UMA modalidade.
 *
 * Ele pode estar em vários times daquela equipe (Sub-15 e Adulto, por
 * exemplo), mas nunca em time de outra equipe nem de outra modalidade —
 * a regra é checada em Jogador::podeJogarPor() e aplicada em todo lugar
 * que cria vínculo de elenco.
 *
 * As colunas são nullable no schema, e não NOT NULL, de propósito: um
 * ambiente que já tenha jogadores cadastrados antes desta regra não pode
 * ter a migration abortada no meio. A obrigatoriedade é garantida na
 * aplicação (validação de cadastro, importação e criação em campo), e
 * quem ficar sem preencher aparece como pendente de revisão na listagem.
 *
 * `onDelete('restrict')` nas duas FKs: apagar uma equipe que ainda tem
 * jogador não pode passar silenciosamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->foreignId('equipe_id')->nullable()->after('id')->constrained('equipes')->restrictOnDelete();
            $table->foreignId('modalidade_id')->nullable()->after('equipe_id')->constrained('modalidades')->restrictOnDelete();

            // A listagem de jogadores é sempre filtrada por equipe e/ou
            // modalidade — é assim que o cadastro é navegado.
            $table->index(['equipe_id', 'modalidade_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jogadores', function (Blueprint $table) {
            $table->dropIndex(['equipe_id', 'modalidade_id']);
            $table->dropConstrainedForeignId('equipe_id');
            $table->dropConstrainedForeignId('modalidade_id');
        });
    }
};
