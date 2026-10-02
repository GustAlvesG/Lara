<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O nome do usuário do Lara que gerou o QR Code de cada liberação.
     *
     * `created_by` sozinho não serve ao rastreio pelo mesmo motivo registrado
     * em `add_attendant_name_to_signature_documents`: o model User fixa a
     * conexão `mysql`, e o manifesto do documento assinado precisa dizer QUEM
     * liberou a assinatura sem depender de o cadastro daquela pessoa existir
     * (ou ter o mesmo nome) anos depois. É um retrato do nome, tirado na hora.
     *
     * Nulo nas liberações anteriores a esta coluna.
     */
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->string('created_by_name', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropColumn('created_by_name');
        });
    }
};
