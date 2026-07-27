<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baixa de pagamento pelo financeiro: além do booleano, registramos quando
     * a baixa foi dada e qual usuário a efetuou.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('coordinator_signed_by');
            $table->timestamp('paid_at')->nullable()->after('paid');
            $table->foreignId('paid_by')->nullable()->after('paid_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn(['paid', 'paid_at']);
        });
    }
};
