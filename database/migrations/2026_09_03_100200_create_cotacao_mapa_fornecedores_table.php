<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As COLUNAS do mapa: cada linha aqui é um fornecedor consultado, e vira uma
 * coluna E..N no XLSX.
 *
 * `questor_cd_entidade` é NULO de propósito para o fornecedor que ainda não
 * existe no cadastro do ERP. O comprador liga para quem quiser cotar, e exigir
 * cadastro prévio no Questor só faria a cotação esperar o cadastro — que é
 * exatamente o contrário do que este módulo serve para fazer.
 *
 * `prazo_entrega` e `condicao_pagamento` são texto livre, não FK. O mapa em uso
 * hoje tem "CONFIRMAR", "3DU" e "Á VISTA" — valores que não existem em
 * TBL_PRAZO_ENTREGA nem em TBL_FINANCEIRO_FORMAS_PAGAMENTO. As tabelas do
 * Questor entram como autocomplete, não como restrição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotacao_mapa_fornecedores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cotacao_mapa_id')
                ->constrained('cotacao_mapas')
                ->cascadeOnDelete();

            // CD_ENTIDADE no Questor, quando o fornecedor é cadastrado lá.
            $table->unsignedInteger('questor_cd_entidade')->nullable()->index();

            // Vai no cabeçalho da coluna do XLSX.
            $table->string('nome', 150);
            $table->string('cnpj', 20)->nullable();
            $table->string('contato', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telefone', 20)->nullable();

            // Linhas 5, 6 e 7 do modelo, nesta ordem.
            $table->string('frete', 20)->nullable();
            $table->string('prazo_entrega', 30)->nullable();
            $table->string('condicao_pagamento', 30)->nullable();

            // Linha FRETE do rodapé. Entra no total, não no subtotal.
            $table->decimal('valor_frete', 18, 2)->default(0);
            $table->decimal('desconto', 18, 2)->default(0);

            $table->date('validade_proposta')->nullable();
            $table->text('observacoes')->nullable();

            // Posição da coluna na grade e no XLSX.
            $table->unsignedSmallInteger('ordem')->default(0);

            $table->timestamps();

            $table->index(['cotacao_mapa_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotacao_mapa_fornecedores');
    }
};
