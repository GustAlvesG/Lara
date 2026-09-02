<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Férias e afastamentos do funcionário.
 *
 * Tabela própria (e não colunas em `employees`) porque são **períodos que se
 * repetem**: um funcionário tem várias férias ao longo dos anos e pode ter
 * mais de um afastamento. Uma coluna só guardaria o último.
 *
 * `start_date` é obrigatória; `end_date` é opcional de propósito — no momento
 * em que o RH registra um afastamento, quase nunca se sabe quando ele termina.
 * Período sem fim é lido como **em aberto**: vale de `start_date` em diante,
 * até alguém fechar.
 *
 * São registros **informativos**: não interferem na importação do espelho de
 * ponto nem no cálculo de saldo. Existem para o cadastro do RH e para o módulo
 * de consulta de funcionários ativos que vem depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('type', ['vacation', 'leave']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('notes')->nullable();
            // Sem FK para `users`: o model User está preso à conexão `mysql`
            // (ver App\Models\User::$connection) e a suíte roda em sqlite — uma
            // constraint aqui quebraria a criação da tabela nos testes.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'type']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_absences');
    }
};
