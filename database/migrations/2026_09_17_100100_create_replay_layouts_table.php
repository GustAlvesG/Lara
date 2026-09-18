<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Layout de logomarcas — mesma regra de dono da tabela de configuração:
     * por esporte OU por quadra, com a herança resolvida no ReplayResolver.
     *
     * O layout é amarrado à ORIENTAÇÃO porque uma composição pensada para
     * 9:16 não serve para 16:9. Por isso a unicidade é (dono, orientação):
     * o Marketing pode deixar os dois formatos prontos e a troca de
     * orientação na configuração passa a valer sem refazer nada.
     *
     * Os campos `overlay_*` guardam o resultado da composição feita pelo
     * OverlayRenderer — é esse arquivo que a API entrega ao sistema de
     * captura. O `overlay_hash` entra no nome do arquivo: URL nova a cada
     * mudança, o que dispensa invalidar cache do outro lado.
     */
    public function up(): void
    {
        Schema::create('replay_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_group_id')->nullable()->constrained('place_groups')->cascadeOnDelete();
            $table->foreignId('place_id')->nullable()->constrained('places')->cascadeOnDelete();
            $table->string('orientation', 12);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->string('overlay_path')->nullable();
            // Só existe quando algum item do layout é GIF animado — exige
            // ffmpeg no servidor. Sem ffmpeg o módulo segue com o PNG.
            $table->string('overlay_animated_path')->nullable();
            $table->string('overlay_hash', 64)->nullable();
            $table->timestamp('overlay_rendered_at')->nullable();
            $table->timestamps();

            $table->unique(['place_group_id', 'orientation']);
            $table->unique(['place_id', 'orientation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_layouts');
    }
};
