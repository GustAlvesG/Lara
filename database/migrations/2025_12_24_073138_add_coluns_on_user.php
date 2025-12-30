<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // DB::transaction(function () {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
                $table->dropColumn('remember_token');
                $table->string('cpf', 14)->after('email')->nullable()->unique();
                $table->string('matricula', 5)->after('cpf')->nullable()->unique();
                $table->integer('status_id')->nullable();
                $table->foreign('status_id')->references('id')->on('status')->onDelete('set null')->after('email');
                $table->timestamp('last_login_at')->after('password')->nullable();
            });
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->after('password')->default('user');
            $table->rememberToken()->after('role');
            $table->dropColumn('cpf');
            $table->dropColumn('matricula');
            $table->dropColumn('last_login_at');
            $table->dropForeign(['status_id']);
            $table->dropColumn('status_id');
        });
    }
};
