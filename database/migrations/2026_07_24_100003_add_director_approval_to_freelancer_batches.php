<?php

use App\Models\FreelancerServiceBatch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aprovação da diretoria por PIN ditado.
     *
     * O diretor não acessa a plataforma (a rede é interna e ele pode estar
     * fora). Ao aprovar o lote, a gerência dispara um e-mail com DOIS PINs —
     * um aprova, outro recusa o lote inteiro. O diretor lê o e-mail, dita o
     * PIN escolhido para a gerência, e a gerência digita no sistema. O PIN é
     * o que prova que a decisão partiu de quem recebeu o e-mail.
     *
     * Os PINs ficam CIFRADOS (e não com hash) porque o e-mail precisa poder
     * ser reenviado com os mesmos números — um reenvio que trocasse os PINs
     * invalidaria o e-mail que o diretor já tem em mãos. Eles nunca são
     * exibidos no painel: só no corpo do e-mail.
     */
    public function up(): void
    {
        Schema::table('freelancer_service_batches', function (Blueprint $table) {
            $table->text('director_approve_pin')->nullable()->after('reviewed_at');
            $table->text('director_reject_pin')->nullable()->after('director_approve_pin');

            // Envio do e-mail: para onde foi e quando. Nulo = ainda não avisado
            // (SMTP fora do ar, por exemplo), e a tela oferece reenviar.
            $table->string('director_email')->nullable()->after('director_reject_pin');
            $table->timestamp('director_notified_at')->nullable()->after('director_email');

            // Decisão: o PIN diz o que o diretor escolheu; guardamos também qual
            // gerente digitou, porque foi ele quem operou o sistema.
            $table->string('director_decision', 20)->nullable()->after('director_notified_at');
            $table->timestamp('director_decided_at')->nullable()->after('director_decision');
            $table->foreignId('director_decided_by')->nullable()->after('director_decided_at')
                ->constrained('users')->nullOnDelete();
            $table->string('director_note')->nullable()->after('director_decided_by');
        });

        Schema::table('freelancer_services', function (Blueprint $table) {
            // Sem *_by: o diretor não é usuário do sistema. Quem digitou fica
            // registrado no lote (director_decided_by).
            $table->timestamp('director_approved_at')->nullable()->after('manager_rejection_reason');
            $table->timestamp('director_rejected_at')->nullable()->after('director_approved_at');
        });

        // Lotes que a gerência já tinha analisado passam a aguardar a diretoria.
        // Sem PIN ainda: a tela mostra "Enviar à diretoria" para gerá-los.
        DB::table('freelancer_service_batches')
            ->where('status', 'reviewed')
            ->update(['status' => FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR]);
    }

    public function down(): void
    {
        DB::table('freelancer_service_batches')
            ->whereIn('status', [
                FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
                FreelancerServiceBatch::STATUS_DIRECTOR_APPROVED,
                FreelancerServiceBatch::STATUS_DIRECTOR_REJECTED,
            ])
            ->update(['status' => 'reviewed']);

        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropColumn(['director_approved_at', 'director_rejected_at']);
        });

        Schema::table('freelancer_service_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('director_decided_by');
            $table->dropColumn([
                'director_approve_pin',
                'director_reject_pin',
                'director_email',
                'director_notified_at',
                'director_decision',
                'director_decided_at',
                'director_note',
            ]);
        });
    }
};
