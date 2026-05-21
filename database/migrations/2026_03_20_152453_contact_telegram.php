<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::create('contact_telegram', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('chat_id');
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        } catch (\Exception $e) {
            // Se algo quebrar, aciona o down() para limpar as tabelas e devolve o erro original
            $this->down();
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
