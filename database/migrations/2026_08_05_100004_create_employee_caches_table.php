<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O cachê de um funcionário num turno.
 *
 * A linha guarda DOIS períodos, e é essa duplicidade que sustenta o fluxo:
 *
 *  - o **previsto** (`expected_*`), informado pelo coordenador na solicitação e
 *    aprovado pela gerência antes de o turno acontecer;
 *  - o **real** (`actual_*`), informado pelo próprio funcionário no momento em
 *    que assina, depois do turno.
 *
 * Mudou o início ou o término, mudou o que a gerência tinha aprovado — e o
 * cachê volta para a reconferência do coordenador e da gerência antes de
 * chegar ao financeiro. Igual ao previsto, segue direto.
 *
 * Os valores são congelados em `expected_price` e `price`: alterar a tabela de
 * faixas da função depois não pode reprecificar o que já tramitou.
 *
 * O estado NÃO é uma coluna: sai da leitura dos carimbos, como nos contratos de
 * freelancer. Um enum aqui seria uma segunda verdade a manter sincronizada com
 * as datas que o painel exibe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('employee_cache_batches')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees');
            // Mesma tabela de funções do freelancer, restrita ao tipo `cache`.
            $table->foreignId('function_freelancer_id')->constrained('function_freelancers');

            $table->string('location');
            $table->string('description')->nullable();
            $table->date('event_date');

            // Previsto — o que o coordenador solicitou e a gerência aprovou.
            $table->time('expected_start_time');
            $table->time('expected_end_time');
            $table->date('expected_end_date');
            $table->unsignedTinyInteger('expected_hours');
            $table->decimal('expected_price', 8, 2);

            // Real — o que o funcionário informou ao assinar.
            $table->time('actual_start_time')->nullable();
            $table->time('actual_end_time')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedTinyInteger('hours')->nullable();
            $table->decimal('price', 8, 2)->nullable();

            // 1ª aprovação: a gerência decide item a item, antes do turno.
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_rejected_at')->nullable();
            $table->foreignId('manager_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_rejection_reason')->nullable();

            // Assinatura do funcionário. Ele não é usuário do sistema: o que
            // identifica a assinatura é o traço guardado e o CPF/matrícula com
            // que ele entrou na tela.
            $table->timestamp('employee_signed_at')->nullable();
            $table->string('employee_signature_path')->nullable();

            // 2ª aprovação — só existe quando o horário real divergiu.
            $table->timestamp('recheck_coordinator_at')->nullable();
            $table->foreignId('recheck_coordinator_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recheck_manager_at')->nullable();
            $table->foreignId('recheck_manager_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recheck_rejected_at')->nullable();
            $table->foreignId('recheck_rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recheck_rejection_reason')->nullable();

            // Financeiro — baixa manual: o cachê não é pago pelo Sicoob.
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A tela de assinatura procura por funcionário e a fila do
            // financeiro procura por data.
            $table->index(['employee_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_caches');
    }
};
