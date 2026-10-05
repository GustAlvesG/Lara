<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem assina um documento. Mais de um por documento (signatário, responsável
 * legal, testemunha), atendidos EM SEQUÊNCIA no balcão: um QR por signatário,
 * na ordem de `position`.
 *
 * O documento só fica `signed` quando o último deles assina.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_signers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('signature_document_id')
                ->constrained('signature_documents')
                ->cascadeOnDelete();

            $table->string('name', 150);

            // Só dígitos. A comparação com o que o tablet digita é feita
            // sobre dígitos dos dois lados — `employees` guarda CPF com e sem
            // máscara, e o mesmo erro não se repete aqui.
            $table->string('cpf', 11)->index();

            /*
             | Associado, quando houver. Sem foreign key: o model Member fixa a
             | conexão `mysql`, e um visitante assina sem cadastro nenhum.
             */
            $table->unsignedBigInteger('member_id')->nullable()->index();

            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();

            $table->enum('role', ['signer', 'guardian', 'witness'])->default('signer');

            $table->unsignedInteger('position')->default(1);

            $table->enum('status', ['pending', 'signed', 'refused', 'canceled', 'expired'])
                ->default('pending')
                ->index();

            $table->timestamp('signed_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->string('refusal_reason', 255)->nullable();

            $table->timestamps();

            $table->unique(['signature_document_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_signers');
    }
};
