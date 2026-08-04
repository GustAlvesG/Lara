<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria dos Pix enviados pelo financeiro de freelancers.
 *
 * UMA LINHA POR TENTATIVA — nada aqui é sobrescrito nem apagado. Se um Pix é
 * rejeitado e reenviado, são duas linhas, e as duas ficam. Esta tabela é a
 * resposta para "esse dinheiro saiu, quando, para quem e por ordem de quem",
 * e uma trilha que se reescreve não responde nada.
 *
 * O `end_to_end_id` é gerado pelo BANCO (na iniciação DICT) e é o que liga a
 * nossa linha à transação real no SPI. É por ele que se consulta o estado e é
 * ele o índice único que impede duas linhas reivindicarem a mesma transação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pix_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('freelancer_service_id')->constrained('freelancer_services');
            $table->foreignId('freelancer_id')->constrained('freelancers');

            /*
             | Gerado por nós ANTES de qualquer chamada. Serve para reconhecer
             | a nossa própria tentativa nos logs e para o job ser reentrante
             | sem criar uma segunda linha para o mesmo trabalho.
             */
            $table->uuid('idempotency_key')->unique();

            /*
             | Identificador fim a fim da transação no SPI (32 alfanuméricos),
             | devolvido pela iniciação DICT. Nulo enquanto a iniciação não
             | acontece; único a partir daí.
             */
            $table->string('end_to_end_id', 32)->nullable()->unique();

            // Chave de destino, como estava no cadastro NA HORA do envio — o
            // cadastro do freelancer pode mudar depois, a trilha não.
            $table->string('pix_key');

            // Titular que o DICT devolveu para essa chave. É o que prova que
            // o dinheiro foi para quem devia.
            $table->string('payee_document', 14)->nullable();
            $table->string('payee_name', 140)->nullable();
            $table->string('payee_key_type', 10)->nullable(); // CPF|CNPJ|EMAIL|TELEFONE|EVP

            $table->decimal('amount', 10, 2);
            $table->string('description', 140)->nullable();

            /*
             | Estado do NOSSO lado do processo:
             |   pending    - linha criada, nada foi chamado ainda
             |   initiated  - iniciação DICT ok, endToEndId reservado, dinheiro parado
             |   sent       - confirmação aceita, banco processando
             |   finalized  - banco confirmou (FINALIZADO). O dinheiro saiu.
             |   rejected   - banco recusou (REJEITADO / NÃO_REALIZADO)
             |   failed     - falhou ANTES de confirmar. Nada saiu. Pode reenviar.
             |   unknown    - a confirmação foi enviada e a resposta se perdeu.
             |                NÃO PODE SER REENVIADO sem antes consultar o banco.
             */
            $table->string('status', 16)->default('pending');

            // Estado cru devolvido pelo Sicoob, sem tradução: EM_PROCESSAMENTO,
            // FINALIZADO, REJEITADO, NÃO_REALIZADO.
            $table->string('bank_state', 20)->nullable();
            $table->text('rejection_detail')->nullable();

            // Payloads para auditoria. Já entram sem Authorization e sem
            // qualquer credencial — a limpeza é responsabilidade do service.
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            // Ambiente em que a tentativa rodou: uma linha de sandbox no meio
            // do histórico de produção precisa ser reconhecível à primeira vista.
            $table->string('environment', 20)->nullable();

            // Sem foreign key para `users` de propósito: o model User fixa a
            // conexão `mysql`, enquanto esta tabela segue a conexão padrão.
            $table->unsignedBigInteger('requested_by')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->timestamps();

            // "Existe pagamento vivo para este contrato?" — a pergunta que a
            // trava de duplicidade faz antes de cada envio.
            $table->index(['freelancer_service_id', 'status']);
            // A reconciliação varre por estado + idade.
            $table->index(['status', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pix_payments');
    }
};
