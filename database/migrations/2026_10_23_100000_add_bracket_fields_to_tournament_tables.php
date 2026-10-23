<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneios, F5 (chaveamento):
 *  - `tournament_categories.bracket_published_at`: o chaveamento só aparece no
 *    site depois que o organizador divulga; gerar de novo tira do site;
 *  - `tournament_matches.third_place`: o jogo do 3º lugar recebe os perdedores
 *    das semifinais (o vencedor de cada jogo segue por `next_match_id`).
 *
 * Idempotente: confere cada coluna antes de criar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournament_categories', 'bracket_published_at')) {
            Schema::table('tournament_categories', function (Blueprint $table) {
                $table->dateTime('bracket_published_at')->nullable()->after('sort_order');
            });
        }

        if (! Schema::hasColumn('tournament_matches', 'third_place')) {
            Schema::table('tournament_matches', function (Blueprint $table) {
                $table->boolean('third_place')->default(false)->after('next_slot');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tournament_matches', 'third_place')) {
            Schema::table('tournament_matches', function (Blueprint $table) {
                $table->dropColumn('third_place');
            });
        }

        if (Schema::hasColumn('tournament_categories', 'bracket_published_at')) {
            Schema::table('tournament_categories', function (Blueprint $table) {
                $table->dropColumn('bracket_published_at');
            });
        }
    }
};
