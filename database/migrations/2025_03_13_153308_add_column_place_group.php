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

        Schema::table('place_groups', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            //Image
            $table->string('image')->nullable()->after('category');
        });
       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->dropColumn('image');
        });
    }
};
