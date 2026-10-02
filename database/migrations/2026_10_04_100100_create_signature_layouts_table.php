<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O papel timbrado: cabeçalho e rodapé da empresa nos documentos assinados.
 *
 * Versionado por linha, como os modelos: salvar não altera a linha em uso,
 * cria a seguinte. O documento guarda a linha vigente no congelamento
 * (`signature_layout_id`), e é dela que o PDF final é re-renderizado — sem
 * isso, trocar o logotipo mudaria a aparência de um contrato já assinado, e o
 * PDF final deixaria de corresponder ao original que a pessoa leu.
 *
 * Pelo mesmo motivo as imagens nunca são sobrescritas no disco: cada envio
 * ganha um arquivo novo, e linhas antigas continuam apontando para o delas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_layouts', function (Blueprint $table) {
            $table->id();

            // Disco privado do módulo. Null = sem imagem: vale o cabeçalho
            // (ou rodapé) de texto padrão.
            $table->string('header_path', 255)->nullable();
            $table->unsignedSmallInteger('header_height_mm')->default(22);

            $table->string('footer_path', 255)->nullable();
            $table->unsignedSmallInteger('footer_height_mm')->default(16);

            // Linha de texto do rodapé: endereço, CNPJ, telefone.
            $table->string('footer_text', 500)->nullable();

            // De margem a margem do PAPEL, e não do texto — para faixas que
            // sangram a página.
            $table->boolean('full_width')->default(false);

            $table->enum('align', ['left', 'center', 'right'])->default('left');

            // Sem foreign key para `users` — ver `signature_templates`.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });

        Schema::table('signature_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('signature_layout_id')->nullable()->after('template_version');
        });
    }

    public function down(): void
    {
        Schema::table('signature_documents', function (Blueprint $table) {
            $table->dropColumn('signature_layout_id');
        });

        Schema::dropIfExists('signature_layouts');
    }
};
