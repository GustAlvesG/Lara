<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lote de cachês solicitado por um coordenador de setor.
 *
 * O lote é a unidade de trabalho de ponta a ponta: o coordenador solicita em
 * lote, a gerência aprova em lote (decidindo item a item) e o financeiro paga
 * em lote. O que NÃO é do lote é a assinatura — ela é de cada funcionário — e,
 * por consequência, a reconferência da divergência, que é item a item.
 *
 * Status:
 *   draft            o coordenador monta a solicitação
 *   sent             aguardando a gerência
 *   manager_reviewed a gerência decidiu (item a item); os aprovados seguem
 *                    para a assinatura dos funcionários
 *   closed           a gerência recusou tudo — não há o que assinar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_cache_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('draft')->index();
            // Setor pelo qual o coordenador solicitou. Fica gravado porque um
            // coordenador pode responder por mais de um setor, e o financeiro
            // precisa saber a qual deles o lote pertence.
            $table->foreignId('sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('title')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_cache_batches');
    }
};
