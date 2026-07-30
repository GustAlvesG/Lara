<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tags das informações do InfoClube, substituindo o campo único `category`.
 *
 * A tabela `tags` já existe (criada para os avisos) e é compartilhada — uma
 * tag "natação" é a mesma nos dois módulos. O vínculo é por VERSÃO
 * (data_info_id), e não pela informação: como cada edição grava uma linha nova
 * em data_infos, isso faz o histórico preservar quais eram as tags à época.
 *
 * A coluna `category` não é removida de propósito: os registros antigos
 * continuam com o valor original e a migração é reversível sem perda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_info_tag', function (Blueprint $table) {
            $table->foreignId('data_info_id')->constrained('data_infos')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->primary(['data_info_id', 'tag_id']);
        });

        if (!Schema::hasColumn('data_infos', 'category')) {
            return;
        }

        // Backfill: cada category existente vira tag da sua versão.
        DB::table('data_infos')
            ->select('id', 'category')
            ->whereNotNull('category')
            ->where('category', '<>', '')
            ->orderBy('id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    foreach (preg_split('/[;,]/', (string) $row->category) as $rawName) {
                        $name = mb_substr(trim(mb_strtolower($rawName)), 0, 50);

                        if ($name === '') {
                            continue;
                        }

                        $tagId = DB::table('tags')->where('name', $name)->value('id');

                        if (!$tagId) {
                            $tagId = DB::table('tags')->insertGetId([
                                'name' => $name,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('data_info_tag')->insertOrIgnore([
                            'data_info_id' => $row->id,
                            'tag_id' => $tagId,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_info_tag');
    }
};
