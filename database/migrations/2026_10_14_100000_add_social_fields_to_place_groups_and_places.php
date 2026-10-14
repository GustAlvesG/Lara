<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Espaços sociais (churrasqueiras, salões, áreas de festa): o que o grupo e o
 * espaço precisam a mais do que uma quadra.
 *
 * No grupo, o prazo para pagar depois da aprovação e os dois limites por sócio
 * — "N a cada X dias" e "N ativas ao mesmo tempo". Todos opcionais: sem valor,
 * o limite não se aplica. Também o texto de regras que o app e o e-mail mostram
 * (ex.: criança abaixo de 4 anos não conta como convidado).
 *
 * No espaço: descrição/observações (ex.: "Proibida a utilização de fogão"),
 * itens fornecidos, capacidade total (sócios + não sócios), quantos convidados
 * não sócios estão incluídos no preço e a cota de extras pagos por pessoa.
 *
 * A categoria existia solta (texto livre, às vezes vazia). Grupo sem categoria
 * sempre foi tratado como quadra, então passa a dizer isso: `esportiva`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('place_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('social_payment_days')->nullable()->after('daily_limit');
            $table->unsignedSmallInteger('social_limit_count')->nullable()->after('social_payment_days');
            $table->unsignedSmallInteger('social_limit_days')->nullable()->after('social_limit_count');
            $table->unsignedSmallInteger('social_active_limit')->nullable()->after('social_limit_days');
            $table->text('social_rules')->nullable()->after('social_active_limit');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->text('amenities')->nullable()->after('description');
            $table->unsignedInteger('capacity')->nullable()->after('price');
            $table->unsignedInteger('max_guests')->default(0)->after('capacity');
            $table->unsignedInteger('extra_guests')->default(0)->after('max_guests');
            $table->decimal('extra_guest_price', 10, 2)->default(0)->after('extra_guests');
        });

        DB::table('place_groups')
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', ''))
            ->update(['category' => 'esportiva']);
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['description', 'amenities', 'capacity', 'max_guests', 'extra_guests', 'extra_guest_price']);
        });

        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn(['social_payment_days', 'social_limit_count', 'social_limit_days', 'social_active_limit', 'social_rules']);
        });
    }
};
