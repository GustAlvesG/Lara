<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            // Evento/local onde o freelancer prestou o serviço.
            $table->string('location')->nullable()->after('function_freelancer_id');

            // status.id é um INT com chave primária própria (não é bigint auto-increment).
            $table->integer('status_id')->default(1)->after('total_hours');
            $table->foreign('status_id')->references('id')->on('status');

            // Assinaturas do contrato. Um serviço assinado não pode mais ser alterado.
            $table->dateTime('freelancer_signed_at')->nullable()->after('status_id');
            $table->dateTime('coordinator_signed_at')->nullable()->after('freelancer_signed_at');
            $table->foreignId('coordinator_signed_by')->nullable()->after('coordinator_signed_at')
                ->constrained('users')->nullOnDelete();

            // Cancelamento (só permitido enquanto não houver nenhuma assinatura).
            $table->dateTime('cancelled_at')->nullable()->after('coordinator_signed_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        DB::table('freelancer_services')->update(['status_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('freelancer_services', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropConstrainedForeignId('coordinator_signed_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'location',
                'status_id',
                'freelancer_signed_at',
                'coordinator_signed_at',
                'cancelled_at',
            ]);
        });
    }
};
