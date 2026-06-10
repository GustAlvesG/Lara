<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avisos', function (Blueprint $table) {
            $table->string('privacy')->default('setor')->after('image');
            $table->dropColumn(['reminder_at', 'reminder_sent']);
        });

        Schema::create('aviso_lembretes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aviso_id')->constrained('avisos')->cascadeOnDelete();
            $table->datetime('remind_at');
            $table->boolean('sent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aviso_lembretes');

        Schema::table('avisos', function (Blueprint $table) {
            $table->dropColumn('privacy');
            $table->datetime('reminder_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
        });
    }
};
