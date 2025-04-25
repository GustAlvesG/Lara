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

        //Remove all the columns from the information table
        Schema::table('information', function (Blueprint $table) {

            if (Schema::hasColumn('information', 'name')) {
                $table->dropColumn('name');
            }
            if (Schema::hasColumn('information', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('information', 'image')) {
                $table->dropColumn('image');
            }
            if (Schema::hasColumn('information', 'category')) {
                $table->dropColumn('category');
            }
            if (Schema::hasColumn('information', 'priceAssociated')) {
                $table->dropColumn('price_associated');
            }
            if (Schema::hasColumn('information', 'priceNotAssociated')) {
                $table->dropColumn('price_not_associated');
            }
            if (Schema::hasColumn('information', 'slots')) {
                $table->dropColumn('slots');
            }
            if (Schema::hasColumn('information', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('information', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('information', 'responsible')) {
                $table->dropColumn('responsible');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //Add the columns back to the information table
        Schema::table('information', function (Blueprint $table) {
            $table->string('name');
            $table->longText('description');
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->decimal('price_associated', 8, 2)->nullable()->default(0.00);
            $table->decimal('price_not_associated', 8, 2)->nullable()->default(0.00);
            $table->string('slots')->nullable();
            $table->string('status')->nullable();
            $table->string('location')->nullable();
            $table->string('responsible')->nullable();
        });
    }
};
