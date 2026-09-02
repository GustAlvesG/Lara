<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Três correções no registro de importação:
 *
 * 1. `user_id` — a importação não tinha dono. Qualquer usuário autenticado que
 *    tivesse o UUID abria a tela de revisão de um arquivo que não subiu e
 *    confirmava a gravação. Agora o dono é gravado e conferido.
 *
 * 2. `status` vira texto para caber `awaiting_review`. O estado "terminei de
 *    analisar e estou esperando uma decisão humana" era representado por
 *    `status = completed` + `phase = detecting` — um "completed" que não
 *    significava concluído, e que obrigava todo acesso à tela de revisão a
 *    repetir os dois `where()`. Vira um estado com nome próprio.
 *
 * 3. `dispatched_at` — a fila é `database` e depende de um `queue:work` vivo.
 *    Sem worker, o job nunca sai de `pending` e a tela fica girando para
 *    sempre, sem dizer por quê. Com o horário do despacho, a tela consegue
 *    avisar que a fila provavelmente está parada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_time_imports', function (Blueprint $table) {
            if (!Schema::hasColumn('comp_time_imports', 'user_id')) {
                // Sem FK para `users`: o model User está preso à conexão
                // `mysql` (ver App\Models\User::$connection) e a suíte roda em
                // sqlite — a constraint quebraria a migration nos testes.
                $table->unsignedBigInteger('user_id')->nullable()->after('uuid')->index();
            }

            if (!Schema::hasColumn('comp_time_imports', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('error_message');
            }
        });

        // Fora do Blueprint acima porque `change()` em coluna que acabou de ser
        // adicionada no mesmo bloco confunde alguns drivers.
        Schema::table('comp_time_imports', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
            $table->string('phase', 20)->default('detecting')->change();
        });
    }

    public function down(): void
    {
        // Registros no estado novo não cabem no enum antigo — sem eles a
        // volta do enum falharia com dado truncado.
        \Illuminate\Support\Facades\DB::table('comp_time_imports')
            ->where('status', 'awaiting_review')
            ->update(['status' => 'completed']);

        Schema::table('comp_time_imports', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->change();
            $table->enum('phase', ['detecting', 'importing', 'confirming'])->default('detecting')->change();
        });

        Schema::table('comp_time_imports', function (Blueprint $table) {
            foreach (['user_id', 'dispatched_at'] as $column) {
                if (Schema::hasColumn('comp_time_imports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
