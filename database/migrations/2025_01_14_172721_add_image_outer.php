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
        // Add image column to outers table
        Schema::table('outers', function (Blueprint $table) {
            $table->string('image')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop image column from outers table
        Schema::table('outers', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
