<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('content')->nullable();
            $table->string('image')->nullable();
            $table->datetime('reminder_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->boolean('expiry_notified')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
