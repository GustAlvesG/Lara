<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O repositório de clipes.
     *
     * `uuid` é a identidade pública do vídeo: é ele que aparece na URL e nas
     * APIs, nunca o id sequencial — o arquivo é servido estaticamente, e um
     * id previsível deixaria a galeria inteira enumerável.
     *
     * `external_id` é o id do clipe no sistema de captura, único: reenviar o
     * mesmo clipe (retry de rede, fila do lado de lá) não duplica registro
     * nem arquivo.
     *
     * `place_group_id` é denormalizado de propósito. A quadra pode ser
     * remanejada de grupo, e o vídeo precisa continuar dizendo em que esporte
     * foi gravado — ler pelo relacionamento devolveria o grupo de hoje, não o
     * da gravação.
     *
     * `schedule_id`/`member_id` só são preenchidos quando havia reserva PAGA
     * (status_id = 1) cobrindo o instante da gravação. Sem reserva, o vídeo
     * existe do mesmo jeito e fica só na galeria da quadra.
     */
    public function up(): void
    {
        Schema::create('replay_videos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->unsignedBigInteger('place_group_id')->nullable();
            $table->foreignId('replay_camera_id')->nullable()->constrained('replay_cameras')->nullOnDelete();
            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('external_id')->nullable()->unique();
            $table->dateTime('recorded_at');
            $table->unsignedSmallInteger('duration_seconds');
            $table->string('orientation', 12);
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->dateTime('expires_at');
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['place_id', 'recorded_at']);
            $table->index(['member_id', 'recorded_at']);
            $table->index(['schedule_id']);
            // O expurgo diário varre por esta coluna.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_videos');
    }
};
