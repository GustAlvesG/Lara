<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de bebidas que o sócio pode somar à locação de um espaço social.
 *
 * `mv_code` é o código do item no MV, digitado à mão pela equipe: o Lara não
 * consulta o MV, só guarda o código para quem lança a venda lá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_beverages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit', 60)->nullable();
            $table->string('mv_code', 60)->nullable();
            $table->decimal('price', 10, 2);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_beverages');
    }
};
