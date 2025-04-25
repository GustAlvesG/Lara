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
        Schema::table('places', function (Blueprint $table) {
            $table->string('image')->nullable();
        });

        //Add status column if not exists
        Schema::table('place_groups', function (Blueprint $table) {
            $table->string('image_horizontal')->nullable();
            //Rename the column
            $table->renameColumn('image', 'image_vertical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        Schema::table('place_groups', function (Blueprint $table) {
            $table->dropColumn('image_horizontal');
            //Rename the column
            $table->renameColumn('image_vertical', 'image');
        });
    }
};
