<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baixa manual de pagamento (19/10/2026): o operador registra no painel que
 * uma reserva foi paga fora da plataforma (dinheiro, Pix direto ao clube,
 * maquininha da secretaria, transferência). Vale para quadras e espaços
 * sociais.
 *
 * O pagamento manual é um SchedulePayment como os da Rede, mas sem
 * `payment_integration_id`. `is_manual` o distingue (não se consulta nem se
 * estorna na Rede), `recorded_by` diz quem deu a baixa e `manual_notes`
 * guarda o comprovante ou a observação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_payments', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('payment_integration_id')->index();
            $table->unsignedBigInteger('recorded_by')->nullable()->after('is_manual');
            $table->string('manual_notes', 500)->nullable()->after('recorded_by');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_payments', function (Blueprint $table) {
            $table->dropIndex(['is_manual']);
            $table->dropColumn(['is_manual', 'recorded_by', 'manual_notes']);
        });
    }
};
