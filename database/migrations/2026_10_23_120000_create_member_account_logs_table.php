<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contas do site (Reservas › Contas do site): o que a equipe fez em cada
 * conta de sócio — corrigir dados, senha provisória, cadastro manual,
 * desativar ou reativar. Só acrescenta; a senha nunca vai para cá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_account_logs')) {
            return;
        }

        Schema::create('member_account_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members', 'id', 'macclog_member_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', 'id', 'macclog_user_fk')->nullOnDelete();
            $table->string('action', 30);
            $table->json('details')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['member_id', 'id'], 'macclog_member_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_account_logs');
    }
};
