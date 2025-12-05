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
        //Drop login_token column from members table if exists
        if (Schema::hasColumn('members', 'login_token')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('login_token');
            });
        }


        Schema::table('members', function (Blueprint $table) {
            $table->longText('image')->nullable()->after('Email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        if (!Schema::hasColumn('members', 'login_token')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('login_token')->nullable()->after('password');
            });
        }
    }
};
