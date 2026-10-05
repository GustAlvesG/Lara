<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autoatendimento de iluminação: quais espaços o sócio pode acender pelo app.
 *
 * Fica no espaço, e não no contator, porque quem escolhe no app é a quadra —
 * e um contator pode alimentar mais de um espaço. Nasce desligado em todos:
 * liberar é decisão de quem administra, não efeito colateral do deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->boolean('self_service_lighting')->default(false)->after('contactor_id');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('self_service_lighting');
        });
    }
};
