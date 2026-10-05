<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada logomarca dentro de um layout.
     *
     * Posição e tamanho em PERCENTUAL do frame (0 a 100), nunca em pixels:
     * a resolução é decisão da câmera e pode mudar sem aviso, e o mesmo
     * layout precisa continuar correto em 1080p ou 4K. A conversão para
     * pixels acontece só na hora de compor o overlay.
     *
     * `animated` marca GIF com mais de um quadro — é o que faz o renderer
     * gerar também a versão WebM com canal alpha.
     */
    public function up(): void
    {
        Schema::create('replay_layout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replay_layout_id')->constrained('replay_layouts')->cascadeOnDelete();
            $table->string('image_path');
            $table->boolean('animated')->default(false);
            $table->decimal('x', 6, 3)->default(0);
            $table->decimal('y', 6, 3)->default(0);
            $table->decimal('width', 6, 3)->default(20);
            $table->decimal('height', 6, 3)->default(20);
            $table->unsignedTinyInteger('opacity')->default(100);
            $table->unsignedSmallInteger('z_index')->default(0);
            $table->timestamps();

            $table->index(['replay_layout_id', 'z_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_layout_items');
    }
};
