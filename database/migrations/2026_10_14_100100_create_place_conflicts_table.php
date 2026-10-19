<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espaços que não podem ser usados no mesmo dia — em geral um espaço social
 * que ocupa (ou atrapalha) uma quadra: o salão montado no ginásio, a festa na
 * área ao lado da quadra.
 *
 * O par é gravado uma vez só, mas vale nos dois sentidos: reserva em qualquer
 * um dos dois ocupa o outro. Quem lê é Place::conflictingPlaceIds().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('conflicting_place_id')->constrained('places')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['place_id', 'conflicting_place_id']);
            $table->index('conflicting_place_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_conflicts');
    }
};
