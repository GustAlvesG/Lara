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
        Schema::table('members', function (Blueprint $table) {
            $table->string("title");
            $table->string("cpf");
            $table->date("birth_date")->nullable();
            $table->string("barcode")->nullable();
            $table->string("name");
            $table->string("telephone")->nullable();
            $table->string("email")->nullable();
            $table->string('password');
            $table->softDeletes();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn("title");
            $table->dropColumn("cpf");
            $table->dropColumn("birth_date");
            $table->dropColumn("barcode");
            $table->dropColumn("name");
            $table->dropColumn("telephone");
            $table->dropColumn("email");
            $table->dropColumn('password');
            $table->dropSoftDeletes();
        });
    }
};
