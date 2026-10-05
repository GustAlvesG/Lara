<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Um e-mail por RESERVA, não por clipe.
     *
     * Uma hora de quadra rende dezenas de apertos no botão; avisar a cada um
     * seria spam e faria o sócio ignorar o aviso que importa. Esta tabela é o
     * que garante a unicidade: a reserva entra aqui quando o e-mail sai, e um
     * clipe que chegue atrasado encontra a linha e não dispara outro.
     *
     * A unicidade é do banco (`unique` em schedule_id), e não só do código:
     * dois Jobs concorrentes da mesma reserva colidem na inserção em vez de
     * mandarem dois e-mails.
     */
    public function up(): void
    {
        Schema::create('replay_member_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id')->unique();
            $table->unsignedBigInteger('member_id');
            $table->unsignedSmallInteger('videos_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replay_member_notifications');
    }
};
