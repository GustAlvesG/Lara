<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galeria de fotos do espaço social (10/11/2026) — o carrossel do site.
 *
 * A capa continua sendo `places.image`; a galeria é o que vem além dela.
 * `file` e `thumb` são caminhos dentro de `public/images` (ex.:
 * `galeria/12/ab3f….jpg`): o arquivo é servido direto pelo servidor web, sem
 * rota do Lara no meio, e o servidor do site o busca de lá.
 *
 * Idempotente (hasTable) e com nome de FK curto: o automático passaria dos 64
 * caracteres do MySQL em algumas combinações.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('place_photos')) {
            return;
        }

        Schema::create('place_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->string('file');
            $table->string('thumb');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('caption', 120)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'position'], 'place_photos_order_idx');
            $table->foreign('place_id', 'place_photos_place_fk')->references('id')->on('places')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_photos');
    }
};
