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
        Schema::table('schedule', function (Blueprint $table) {
            //Drop columns associated_name
            $table->dropColumn('associated_name');
            $table->dropColumn('associated_telephone');
            $table->dropColumn('associated_cpf');

            //Create columns member_id
            $table->unsignedBigInteger('member_id')->after('id')->nullable();
            $table->foreign('member_id')->references('id')->on('members');

            $table->decimal('price', 8, 2)->after("description")->nullable();
            $table->string('status_payment')->after('price')->nullable();

            $table->string('status')->after('status_payment')->nullable();
        });

        Schema::table('places', function (Blueprint $table) {
            //Create columns schedule_id
            $table->decimal('price', 8, 2)->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule', function (Blueprint $table) {
            //Create columns associated_name
            $table->string('associated_name')->after('id')->nullable();
            $table->string('associated_telephone')->after('associated_name')->nullable();
            $table->string('associated_cpf')->after('associated_telephone')->nullable();

            //Drop columns member_id
            $table->dropForeign(['member_id']);
            $table->dropColumn('member_id');

            $table->dropColumn('price');
            $table->dropColumn('status_payment');
            $table->dropColumn('status');
        });

        Schema::table('places', function (Blueprint $table) {
            //Drop columns schedule_id
            $table->dropColumn('price');
        });
    }
};
