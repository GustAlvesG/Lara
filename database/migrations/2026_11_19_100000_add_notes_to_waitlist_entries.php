<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de Espera (19/11/2026): observação livre da inscrição (pessoa × turma),
 * diferente da observação do cadastro da pessoa. Aparece na fila da turma,
 * abaixo do nome. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('waitlist_entries') || Schema::hasColumn('waitlist_entries', 'notes')) {
            return;
        }

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('waitlist_entries', 'notes')) {
            Schema::table('waitlist_entries', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
