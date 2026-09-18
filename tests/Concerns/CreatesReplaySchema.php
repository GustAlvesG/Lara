<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema mínimo para os testes do Replay.
 *
 * Sem `RefreshDatabase` pelo mesmo motivo já registrado em
 * `CreatesFreelancerPixSchema` e `MigratesPlacarSchema`: a cadeia completa de
 * migrations não roda hoje no SQLite (`members.title` é adicionada duas
 * vezes, em `add_columns_member` e `tourments`). As tabelas legadas
 * (place_groups, places, schedules, members) são montadas aqui com as colunas
 * que o módulo lê.
 *
 * As migrations do PRÓPRIO Replay são as de verdade, aplicadas a partir do
 * arquivo: elas são parte do que está sendo testado, e uma cópia aqui
 * deixaria de acusar divergência entre o teste e o que vai para produção.
 */
trait CreatesReplaySchema
{
    protected function createReplaySchema(): void
    {
        Schema::create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('place_group_id')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cpf')->nullable();
            // Minúsculo, como no banco de verdade — o $fillable do model lista
            // 'Email'/'Name' em maiúscula, mas a coluna é esta.
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('place_id');
            $table->dateTime('start_schedule');
            $table->dateTime('end_schedule');
            $table->unsignedBigInteger('status_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Sanctum: os testes da API do Replay autenticam por token.
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        foreach ([
            '2026_09_17_100000_create_replay_settings_table.php',
            '2026_09_17_100100_create_replay_layouts_table.php',
            '2026_09_17_100200_create_replay_layout_items_table.php',
            '2026_09_17_100300_create_replay_cameras_table.php',
            '2026_09_17_100400_create_replay_videos_table.php',
            '2026_09_17_100500_create_replay_member_notifications_table.php',
            '2026_09_17_100600_create_replay_api_clients_table.php',
        ] as $migration) {
            (require base_path("database/migrations/{$migration}"))->up();
        }
    }
}
