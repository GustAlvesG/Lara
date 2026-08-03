<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A foto do sócio (importada do campo Photo da base MultiClubes) deixou de ser
     * usada pelo sistema — a coluna sai junto com a integração que a alimentava.
     */
    public function up(): void
    {
        if (Schema::hasColumn('members', 'image')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('members', 'image')) {
            Schema::table('members', function (Blueprint $table) {
                $table->longText('image')->nullable()->after('Email');
            });
        }
    }
};
