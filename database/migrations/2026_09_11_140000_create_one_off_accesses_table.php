<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liberação pontual: a entrada de alguém sem vínculo com empresa parceira,
     * Uber ou contrato de freelancer, para o caso extraordinário em que não há
     * tempo de fazer o cadastro formal.
     *
     * Vale para UMA entrada, só no dia em que foi criada (`access_date`). O CPF
     * é a chave: a portaria digita o CPF como já faz para terceirizado e
     * freelancer, e a liberação aparece no mesmo resultado.
     */
    public function up(): void
    {
        Schema::create('one_off_accesses', function (Blueprint $table) {
            $table->id();
            // Só dígitos — é assim que a portaria procura.
            $table->string('cpf', 11)->index();
            $table->string('name');
            $table->text('reason');
            $table->string('image')->nullable();
            $table->date('access_date')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->unsignedBigInteger('canceled_by_user')->nullable();
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->timestamps();

            $table->foreign('created_by_user')->references('id')->on('users')->onDelete('set null');
            $table->foreign('canceled_by_user')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_off_accesses');
    }
};
