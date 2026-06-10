<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_assistant_override_windows', function (Blueprint $table) {
            $table->enum('state', ['on', 'off'])->default('on')->after('turn_off_at');
        });
    }

    public function down(): void
    {
        Schema::table('home_assistant_override_windows', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
