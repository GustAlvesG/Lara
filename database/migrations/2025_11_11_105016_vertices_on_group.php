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
        #Adding vertices column to groups table
        Schema::table('place_groups', function (Blueprint $table) {
            $table->string('vertices')->nullable();
            $table->string('icon')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        #Removing vertices column from groups table
        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('vertices');
            $table->dropColumn('icon');
        });
    }
};
