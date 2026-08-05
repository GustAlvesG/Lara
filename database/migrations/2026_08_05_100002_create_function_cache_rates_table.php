<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faixas de valor do cachê: uma linha por hora cheia, de 2h a 11h.
 *
 * O cachê NÃO é proporcional — não existe "valor da hora × horas". Cada faixa
 * tem seu próprio valor de tabela, negociado, e a duração do turno apenas diz
 * em qual faixa o pagamento cai. Por isso as faixas são linhas e não uma
 * conta: 5h pode valer mais que 2,5 × o valor de 2h, ou menos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('function_cache_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('function_freelancer_id')->constrained()->cascadeOnDelete();
            // Horas cheias da faixa (2 a 11). O teto e o piso são regra de
            // negócio e vivem no model, não aqui.
            $table->unsignedTinyInteger('hours');
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->unique(['function_freelancer_id', 'hours']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('function_cache_rates');
    }
};
