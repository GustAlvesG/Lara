<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de Espera (18/11/2026): a pessoa pode ser cadastrada SEM CPF.
 *
 * As listas antigas vêm de planilhas sem CPF e não dá para ir atrás de todo
 * mundo antes de importar. O cadastro aceita a pessoa sem CPF; qualquer ação
 * posterior (ligação, saída, reativação, nova turma, editar o cadastro) exige
 * o CPF antes — a régua fica no WaitlistService e no WaitlistPersonRequest.
 *
 * O índice único continua: o MySQL aceita vários NULL num UNIQUE.
 * Idempotente: só altera se a coluna ainda for NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('waitlist_people') || !Schema::hasColumn('waitlist_people', 'cpf')) {
            return;
        }

        Schema::table('waitlist_people', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Só volta a NOT NULL se ninguém estiver sem CPF: nada é apagado.
        if (!Schema::hasTable('waitlist_people') || DB::table('waitlist_people')->whereNull('cpf')->exists()) {
            return;
        }

        Schema::table('waitlist_people', function (Blueprint $table) {
            $table->string('cpf', 11)->nullable(false)->change();
        });
    }
};
