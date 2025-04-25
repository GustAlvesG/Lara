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
        //Add status column if not exists
        Schema::table('place_groups', function (Blueprint $table) {
            $table->boolean('status')->default(1);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->boolean('status')->default(1);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
