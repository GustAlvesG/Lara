<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_workers', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->unsignedBigInteger('updated_by_user')->nullable()->after('created_by_user');
            $table->foreign('created_by_user')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_user')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('company_access_rules', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user')->nullable();
            $table->unsignedBigInteger('updated_by_user')->nullable()->after('created_by_user');
            $table->foreign('created_by_user')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by_user')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('company_workers', function (Blueprint $table) {
            $table->dropForeign(['created_by_user']);
            $table->dropForeign(['updated_by_user']);
            $table->dropColumn(['created_by_user', 'updated_by_user']);
        });

        Schema::table('company_access_rules', function (Blueprint $table) {
            $table->dropForeign(['created_by_user']);
            $table->dropForeign(['updated_by_user']);
            $table->dropColumn(['created_by_user', 'updated_by_user']);
        });
    }
};
