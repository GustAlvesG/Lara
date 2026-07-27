<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lote de contratos que o coordenador monta e envia para a aprovação da
     * gerência. O lote nasce como rascunho, é enviado e depois analisado — a
     * decisão, porém, é por contrato (ver as colunas manager_* em
     * freelancer_services), então o lote guarda só o trâmite.
     */
    public function up(): void
    {
        Schema::create('freelancer_service_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('draft')->index();

            // Coordenador que montou e enviou.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            // Gerente (role `admin`) que analisou.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });

        Schema::table('freelancer_services', function (Blueprint $table) {
            // Último lote em que o contrato entrou. Continua apontando para o
            // lote depois da análise — é por ele que se sabe onde foi decidido.
            $table->foreignId('batch_id')->nullable()->after('coordinator_signature_path')
                ->constrained('freelancer_service_batches')->nullOnDelete();

            // Decisão da gerência, sempre a mais recente. Um contrato recusado
            // volta a ficar disponível para entrar num novo lote.
            $table->timestamp('manager_approved_at')->nullable()->after('batch_id');
            $table->foreignId('manager_approved_by')->nullable()->after('manager_approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('manager_rejected_at')->nullable()->after('manager_approved_by');
            $table->foreignId('manager_rejected_by')->nullable()->after('manager_rejected_at')
                ->constrained('users')->nullOnDelete();
            $table->string('manager_rejection_reason')->nullable()->after('manager_rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropConstrainedForeignId('manager_approved_by');
            $table->dropConstrainedForeignId('manager_rejected_by');
            $table->dropColumn(['manager_approved_at', 'manager_rejected_at', 'manager_rejection_reason']);
        });

        Schema::dropIfExists('freelancer_service_batches');
    }
};
