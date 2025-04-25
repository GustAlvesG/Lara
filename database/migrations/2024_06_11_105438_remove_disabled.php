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
        //Alter table information and remove disabled default value
        Schema::table('information', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->string('category')->nullable()->change();
            $table->string('slots')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('location')->nullable()->change();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
