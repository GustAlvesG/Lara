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
        #Relacionamento status / schedule
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->integer('status_id')->after('price')->nullable();
            $table->foreign('status_id')->references('id')->on('status')->onDelete('set null');
        });



    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        #Remover relacionamento status / schedule
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
            $table->string('status')->after('price');

        });
    }
};
