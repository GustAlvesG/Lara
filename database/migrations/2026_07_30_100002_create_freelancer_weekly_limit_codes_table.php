<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Código de liberação enviado por e-mail ao coordenador do Comercial,
     * para o caso de ele não estar presente para digitar o próprio PIN. Ele
     * lê o código e dita para quem está registrando o contrato.
     *
     * O código é preso ao trio coordenador + freelancer + data do serviço: um
     * código pedido para um contrato não libera outro.
     */
    public function up(): void
    {
        Schema::create('freelancer_weekly_limit_codes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('freelancers')->cascadeOnDelete();
            $table->date('start_date');

            // Guardado em hash: quem tem acesso ao banco não deve conseguir
            // liberar um contrato em nome do coordenador.
            $table->string('code_hash');
            $table->string('sent_to');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            // Busca do código pendente na hora de conferir o que foi ditado.
            $table->index(['coordinator_id', 'freelancer_id', 'start_date'], 'freelancer_weekly_codes_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_weekly_limit_codes');
    }
};
