<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos de documento da assinatura presencial — o texto do termo, da ficha
 * ou do contrato que o tablet vai exibir.
 *
 * O modelo é VERSIONADO POR LINHA: editar não altera a linha em uso, cria a
 * versão seguinte e desativa a anterior. Um documento assinado aponta para a
 * linha exata que gerou o texto que a pessoa leu, e revisar o modelo nunca
 * reescreve, retroativamente, o que já foi firmado.
 *
 * É a mesma garantia das redações de contrato de freelancer, por outro
 * caminho: lá o texto mora em arquivo (`contract/vN/`), versionado no git e
 * lacrado por teste de sha256, porque quem revisa é o jurídico, por commit.
 * Aqui quem escreve o modelo é a gerência, por tela — não há commit para
 * versionar, então a versão mora no banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_templates', function (Blueprint $table) {
            $table->id();

            /*
             | Agrupa as versões de um mesmo modelo. A primeira versão nasce
             | apontando para o próprio id (ver SignatureTemplate::booted) —
             | assim "todas as versões deste modelo" é uma consulta só, sem
             | tabela de cabeçalho para uma informação que é só um vínculo.
             */
            $table->unsignedBigInteger('root_id')->nullable()->index();
            $table->unsignedInteger('version')->default(1);

            $table->string('name', 150);
            $table->text('description')->nullable();

            /*
             | O corpo do documento, em HTML, já saneado por App\Support\
             | HtmlSanitizer na gravação. Aceita as variáveis do modelo na
             | forma [[nome_da_variavel]], substituídas no congelamento.
             */
            $table->longText('body_html');

            /*
             | Variáveis esperadas: [{"key":"local","label":"Local","required":true}].
             | É o que a tela do atendente transforma em formulário.
             */
            $table->json('variables')->nullable();

            // Onde o traço entra no documento final. Um marcador no corpo, e
            // não coordenadas: o PDF final é re-renderizado a partir do HTML
            // congelado, então a posição é a do fluxo do texto.
            $table->string('signature_placeholder', 60)->default('[[assinatura]]');

            /*
             | Coordenadas (página, x, y, largura) para o carimbo cirúrgico no
             | PDF. Nasce sem uso: existe para o dia em que a finalização
             | passar a carimbar o PDF original em vez de re-renderizá-lo.
             */
            $table->json('signature_position')->nullable();

            $table->boolean('requires_photo')->default(true);

            /*
             | Como o tablet confere quem está assinando: os 4 primeiros
             | dígitos do CPF (padrão), o CPF inteiro, ou nenhuma conferência.
             | Sempre validado no servidor — o CPF cadastrado não vai para a
             | tela em nenhum dos três casos.
             */
            $table->enum('identity_check', ['partial', 'full', 'none'])->default('partial');

            // Prazo de guarda em meses. Gravado desde já; a exclusão física
            // não faz parte desta entrega.
            $table->unsignedInteger('retention_months')->nullable();

            $table->boolean('active')->default(true);

            /*
             | Autor. Sem foreign key para `users` pelo mesmo motivo de
             | `cotacao_mapas`: o model User fixa a conexão `mysql`, enquanto
             | esta tabela segue a conexão padrão.
             */
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            // Uma versão por modelo, e a lista de modelos vigentes.
            $table->unique(['root_id', 'version']);
            $table->index(['active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_templates');
    }
};
